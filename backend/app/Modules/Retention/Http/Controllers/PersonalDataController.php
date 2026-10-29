<?php

declare(strict_types=1);

namespace App\Modules\Retention\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\StepUp;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Retention\Jobs\LocatePersonalData;
use App\Modules\Retention\PersonalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Personal data requests (specification §4.26): received, located, reviewed,
 * then carried out or rejected with a justification. Deleting or anonymizing
 * also needs a fresh two-factor code. Everything needs "Manage personal data
 * requests".
 */
final class PersonalDataController extends Controller
{
    public function __construct(private readonly PersonalData $personal, private readonly AuditWriter $audit) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $rows = DB::table('personal_data_requests')->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => $rows->map(fn ($r) => $this->present($r, false))->values()->all()]);
    }

    public function show(string $request): JsonResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $r = $this->find($request);
        $this->audit->record('personal_data.viewed', 'security', null, 'personal_data_request', (int) $r->id);

        return response()->json(['data' => $this->present($r, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $data = $request->validate([
            'type' => ['required', Rule::in(PersonalData::TYPES)],
            'subject' => ['required', 'array'],
            'subject.email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'subject.identifier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject.user' => ['sometimes', 'nullable', 'uuid'],
        ]);
        $norm = PersonalData::normalize($data['subject']);
        if ($norm['subject'] === []) {
            throw ValidationException::withMessages(['subject' => __('retention.pd.subject_required')]);
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (int) DB::table('personal_data_requests')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => app(TenantContext::class)->organizationId(), 'created_at' => $now, 'updated_at' => $now,
            'created_by' => Auth::id(), 'updated_by' => Auth::id(), 'type' => $data['type'],
            'subject' => Crypt::encryptString((string) json_encode($norm['subject'], JSON_UNESCAPED_UNICODE)), 'subject_hash' => $norm['hash'], 'status' => 'received',
        ]);
        $this->audit->record('personal_data.requested', 'security', null, 'personal_data_request', $id, ['type' => $data['type'], 'identifiers' => array_keys($norm['subject'])]);
        LocatePersonalData::dispatch($id)->afterCommit();

        return response()->json(['data' => $this->present(DB::table('personal_data_requests')->where('id', $id)->first(), true)], 201);
    }

    /** Approves the request and carries it out; deleting or anonymizing needs a fresh two-factor code. */
    public function execute(Request $request, string $uuid, StepUp $stepUp): JsonResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $r = $this->find($uuid);
        /** @var User $user */
        $user = Auth::user();
        if (in_array($r->type, ['delete', 'anonymize'], true)) {
            $stepUp->require($request, $user);
        }
        try {
            $this->personal->execute($r, $user, trim($data['reason']));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['request' => $e->getMessage()]);
        }

        return response()->json(['data' => $this->present($this->find($uuid), true)]);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $r = $this->find($uuid);
        /** @var User $user */
        $user = Auth::user();
        try {
            $this->personal->reject($r, $user, trim($data['reason']));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['request' => $e->getMessage()]);
        }

        return response()->json(['data' => $this->present($this->find($uuid), true)]);
    }

    /** Searches again (a request that failed, or findings that are out of date before review). */
    public function locate(string $uuid): JsonResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $r = $this->find($uuid);
        abort_unless(in_array($r->status, ['received', 'awaiting_review', 'failed'], true), 422, __('retention.pd.not_awaiting'));
        DB::table('personal_data_requests')->where('id', $r->id)->update(['status' => 'received', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        LocatePersonalData::dispatch((int) $r->id);

        return response()->json(['data' => $this->present($this->find($uuid), true)], 202);
    }

    /** The export bundle of a completed export request. */
    public function file(string $uuid): StreamedResponse
    {
        Gate::authorize('system.manage_personal_data_requests');
        $r = $this->find($uuid);
        $file = $r->result_file_id === null ? null : StoredFile::query()->whereKey((int) $r->result_file_id)->whereNull('deleted_at')->first();
        abort_if($file === null, 404);
        $this->audit->record('personal_data.export_downloaded', 'export', null, 'personal_data_request', (int) $r->id);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => 'application/json', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    private function present(object $r, bool $full): array
    {
        $users = DB::table('users')->whereIn('id', array_filter([$r->created_by, $r->completed_by]) ?: [0])->pluck('name', 'id');
        $justification = $r->justification_id === null ? null : DB::table('justifications')->where('id', $r->justification_id)->value('reason_text');
        $out = [
            'uuid' => strtolower((string) $r->uuid), 'type' => $r->type, 'status' => $r->status,
            'created_at' => self::iso($r->created_at), 'created_by' => $users[$r->created_by] ?? null,
            'completed_at' => self::iso($r->completed_at), 'completed_by' => $r->completed_by === null ? null : ($users[$r->completed_by] ?? null),
            'reason' => $justification, 'has_file' => $r->result_file_id !== null,
        ];
        if ($full) {
            $out['subject'] = $this->personal->subjectOf($r);
            $out['findings'] = $r->findings === null ? null : json_decode((string) $r->findings, true);
        }

        return $out;
    }

    private function find(string $uuid): object
    {
        $r = Str::isUuid($uuid) ? DB::table('personal_data_requests')->where('uuid', strtolower($uuid))->where('organization_id', app(TenantContext::class)->organizationId())->first() : null;
        abort_if($r === null, 404);

        return $r;
    }

    private static function iso(mixed $v): ?string
    {
        return $v === null ? null : Carbon::parse((string) $v, 'UTC')->toIso8601ZuluString();
    }
}
