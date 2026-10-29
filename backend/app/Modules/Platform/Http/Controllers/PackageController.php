<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\Jobs\ApplyPackage;
use App\Modules\Platform\Packages\PackageService;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Configuration packages and environment comparison (specification §4.10,
 * §4.22, ADR-0052), for holders of "Manage configuration packages".
 */
final class PackageController extends Controller
{
    public function __construct(private readonly PackageService $packages, private readonly AuditWriter $audit) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_packages');
        $rows = DB::table('config_packages')->orderByDesc('id')->limit(200)->get();
        $names = DB::table('users')->whereIn('id', $rows->pluck('created_by')->filter())->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn ($r) => $this->summary($r, $names[$r->created_by] ?? null))->all()]);
    }

    public function catalog(): JsonResponse
    {
        Gate::authorize('system.manage_packages');

        return response()->json(['data' => $this->packages->catalog()]);
    }

    public function export(Request $request, FileStore $files): JsonResponse
    {
        Gate::authorize('system.manage_packages');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'objects' => ['required', 'array', 'min:1', 'max:500'],
            'objects.*.type' => ['required', Rule::in(['application', 'form', 'email_template', 'document_template', 'download_profile', 'report', 'dashboard'])],
            'objects.*.uuid' => ['required', 'uuid'],
        ]);
        $user = $this->user();
        $package = $this->packages->export($data['name'], $data['objects'], $user);
        $json = (string) json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $tmp = tempnam(sys_get_temp_dir(), 'lcfpkg');
        file_put_contents((string) $tmp, $json);
        $file = $files->storeGenerated((string) $tmp, Str::slug($data['name']) ?: 'package', 'json', 'application/json', 'config_package', ['user_id' => $user->id]);
        @unlink((string) $tmp);
        $id = $this->insert('export', $data['name'], $this->summaryManifest($package), $file->id, $package['checksum'], 'validated', $user);
        // What was exported is what this environment had: the baseline a later import of the same package compares with.
        DB::table('config_packages')->where('id', $id)->update([
            'resolution' => json_encode(['applied' => collect($package['objects'])->mapWithKeys(static fn ($o) => [$o['uuid'] => $o['hash']])->all(),
                'keys' => collect($package['objects'])->mapWithKeys(static fn ($o) => [$o['uuid'] => $o['key']])->all()], JSON_UNESCAPED_UNICODE),
            'applied_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        $this->audit->record('package.exported', 'config', null, 'config_package', $id, ['name' => $data['name'], 'objects' => count($package['objects'])], $user->id);

        return response()->json(['data' => $this->detail(DB::table('config_packages')->where('id', $id)->first())], 201);
    }

    public function import(Request $request, FileStore $files): JsonResponse
    {
        Gate::authorize('system.manage_packages');
        $request->validate(['file' => ['required', 'file', 'max:'.(PackageService::MAX_BYTES / 1024)]]);
        $user = $this->user();
        $json = (string) file_get_contents((string) $request->file('file')?->getRealPath());
        $package = $this->packages->read($json);
        $report = $this->packages->analyze($package);
        $reportId = $this->packages->storeReport($package, $report, $user);
        $tmp = tempnam(sys_get_temp_dir(), 'lcfpkg');
        file_put_contents((string) $tmp, $json);
        $file = $files->storeGenerated((string) $tmp, Str::slug((string) ($package['name'] ?? 'package')) ?: 'package', 'json', 'application/json', 'config_package', ['user_id' => $user->id]);
        @unlink((string) $tmp);
        $status = $report['ambiguous'] || collect($report['objects'])->contains(fn ($o) => ! in_array($o['state'], ['new', 'same'], true) || $o['missing'] !== []) ? 'conflicts' : 'validated';
        $id = $this->insert('import', (string) ($package['name'] ?? ''), $package, $file->id, (string) $package['checksum'], $status, $user,
            (string) ($package['source']['environment'] ?? ''), $reportId, ['report' => $report]);
        $this->audit->record('package.uploaded', 'config', null, 'config_package', $id, ['name' => $package['name'] ?? null, 'objects' => count($package['objects']), 'ambiguous' => $report['ambiguous']], $user->id);

        return response()->json(['data' => $this->detail(DB::table('config_packages')->where('id', $id)->first())], 201);
    }

    /** Compares a package (or another environment's full export) with this one, without importing it. */
    public function compare(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_packages');
        $request->validate(['file' => ['required', 'file', 'max:'.(PackageService::MAX_BYTES / 1024)]]);
        $package = $this->packages->read((string) file_get_contents((string) $request->file('file')?->getRealPath()));
        $report = $this->packages->analyze($package);
        $id = $this->packages->storeReport($package, $report, $this->user());

        return response()->json(['data' => $this->report(DB::table('environment_drift_reports')->where('id', $id)->first())], 201);
    }

    public function reports(): JsonResponse
    {
        Gate::authorize('system.manage_packages');

        return response()->json(['data' => DB::table('environment_drift_reports')->orderByDesc('id')->limit(100)->get()->map(fn ($r) => $this->report($r, false))->all()]);
    }

    public function showReport(string $report): JsonResponse
    {
        Gate::authorize('system.manage_packages');
        $r = DB::table('environment_drift_reports')->where('uuid', strtolower($report))->first();
        abort_if($r === null, 404);

        return response()->json(['data' => $this->report($r)]);
    }

    public function show(string $package): JsonResponse
    {
        Gate::authorize('system.manage_packages');

        return response()->json(['data' => $this->detail($this->find($package))]);
    }

    public function download(string $package): StreamedResponse
    {
        Gate::authorize('system.manage_packages');
        $row = $this->find($package);
        $file = $row->file_id === null ? null : StoredFile::query()->find($row->file_id);
        abort_if($file === null, 404);
        $this->audit->record('package.downloaded', 'export', null, 'config_package', (int) $row->id, ['name' => $row->name]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => 'application/json']);
    }

    public function apply(Request $request, string $package): JsonResponse
    {
        Gate::authorize('system.manage_packages');
        $row = $this->find($package);
        abort_if($row->direction !== 'import' || ! in_array($row->status, ['validated', 'conflicts', 'failed'], true), 409, __('packages.not_applicable'));
        $data = $request->validate([
            'choices' => ['sometimes', 'array'],
            'choices.*.action' => ['required', Rule::in(['take', 'keep', 'rename'])],
            'choices.*.key' => ['sometimes', 'nullable', 'string', 'max:48'],
            'confirm_blocking' => ['sometimes', 'boolean'],
            'confirm_destructive' => ['sometimes', 'boolean'],
        ]);
        $manifest = json_decode((string) $row->manifest, true);
        // Compared again now: this environment may have changed since the upload.
        $report = $this->packages->analyze($manifest);
        $choices = $this->packages->resolve($report, (array) ($data['choices'] ?? []));
        $user = $this->user();
        DB::table('config_packages')->where('id', $row->id)->update([
            'status' => 'applying', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'updated_by' => $user->id,
            'conflict_report' => json_encode(['report' => $report], JSON_UNESCAPED_UNICODE),
            'resolution' => json_encode(['choices' => $choices, 'options' => ['confirm_blocking' => (bool) ($data['confirm_blocking'] ?? false), 'confirm_destructive' => (bool) ($data['confirm_destructive'] ?? false)]], JSON_UNESCAPED_UNICODE),
        ]);
        $this->audit->record('package.apply_started', 'config', null, 'config_package', (int) $row->id, ['name' => $row->name], $user->id);
        ApplyPackage::dispatch((int) $row->id, $user->id);

        return response()->json(['data' => $this->detail($this->find($package))], 202);
    }

    private function insert(string $direction, string $name, array $manifest, ?int $fileId, string $checksum, string $status, User $user, ?string $source = null, ?int $reportId = null, ?array $conflicts = null): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');

        return (int) DB::table('config_packages')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $user->organization_id, 'created_at' => $now, 'updated_at' => $now, 'created_by' => $user->id, 'updated_by' => $user->id,
            'direction' => $direction, 'name' => mb_substr($name, 0, 255), 'manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE), 'file_id' => $fileId, 'checksum' => $checksum,
            'source_environment' => $source === null ? null : mb_substr($source, 0, 128), 'status' => $status,
            'conflict_report' => $conflicts === null ? null : json_encode($conflicts, JSON_UNESCAPED_UNICODE), 'drift_report_id' => $reportId,
        ]);
    }

    /**
     * An exported package's manifest is kept without the content (the file has it).
     *
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function summaryManifest(array $package): array
    {
        return ['format' => $package['format'], 'source' => $package['source'], 'objects' => array_map(static fn ($o) => array_intersect_key($o, array_flip(['type', 'uuid', 'key', 'label', 'hash'])), $package['objects'])];
    }

    private function find(string $uuid): object
    {
        $row = Str::isUuid($uuid) ? DB::table('config_packages')->where('uuid', strtolower($uuid))->first() : null;
        abort_if($row === null, 404);

        return $row;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }

    /** @return array<string, mixed> */
    private function summary(object $r, ?string $by): array
    {
        $manifest = json_decode((string) $r->manifest, true) ?: [];

        return [
            'uuid' => strtolower((string) $r->uuid), 'direction' => $r->direction, 'name' => $r->name, 'status' => $r->status,
            'objects' => count((array) ($manifest['objects'] ?? [])), 'source' => $r->source_environment, 'by' => $by,
            'created_at' => Carbon::parse((string) $r->created_at, 'UTC')->toIso8601ZuluString(),
            'applied_at' => $r->applied_at === null ? null : Carbon::parse((string) $r->applied_at, 'UTC')->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(object $r): array
    {
        $manifest = json_decode((string) $r->manifest, true) ?: [];
        $conflicts = json_decode((string) ($r->conflict_report ?? 'null'), true) ?: [];
        $resolution = json_decode((string) ($r->resolution ?? 'null'), true) ?: [];
        $by = DB::table('users')->where('id', $r->created_by)->value('name');

        return $this->summary($r, $by === null ? null : (string) $by) + [
            'source_detail' => $manifest['source'] ?? null,
            'items' => array_map(static fn ($o) => array_intersect_key($o, array_flip(['type', 'uuid', 'key', 'label'])), (array) ($manifest['objects'] ?? [])),
            'report' => $conflicts['report'] ?? null,
            'choices' => $resolution['choices'] ?? null,
            'results' => $resolution['results'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function report(object $r, bool $full = true): array
    {
        $objects = json_decode((string) $r->metadata_differences, true) ?: [];

        return [
            'uuid' => strtolower((string) $r->uuid), 'source' => $r->source_label, 'target' => $r->target_label, 'ambiguous' => (bool) $r->is_ambiguous,
            'created_at' => Carbon::parse((string) $r->created_at, 'UTC')->toIso8601ZuluString(),
            'counts' => collect($objects)->countBy('state')->all() ?: (object) [],
        ] + ($full ? ['objects' => $objects, 'schema' => json_decode((string) $r->schema_differences, true) ?: []] : []);
    }
}
