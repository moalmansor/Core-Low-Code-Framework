<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Modules\Audit\AuditWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Storage quotas (specification §4.26): per organization, application or
 * form, a warning threshold and a hard limit on the bytes of stored files.
 * Usage is measured nightly for the screen and the warning state; the hard
 * limit is checked live at upload and when a file is attached to a record,
 * so a quota can never be overrun between two measurements.
 */
final class StorageQuotas
{
    public const SCOPES = ['organization', 'application', 'form'];

    public function __construct(private readonly AuditWriter $audit) {}

    /** Bytes of live files in a scope. */
    public function usage(string $scope, int $id): int
    {
        $q = DB::table('files')->whereNull('files.deleted_at')->where('files.is_temporary', false);
        match ($scope) {
            'form' => $q->where('files.form_id', $id),
            'application' => $q->whereIn('files.form_id', DB::table('forms')->where('application_id', $id)->select('id')),
            default => $q->where('files.organization_id', $id),
        };

        return (int) $q->sum('files.size_bytes');
    }

    /**
     * Refuses a new file of `$bytes` when it would take a quota that covers
     * it past its hard limit (the organization's, and the form's and its
     * application's when the file belongs to a form).
     */
    public function assertRoom(int $organizationId, ?int $formId, int $bytes): void
    {
        $scopes = [['organization', $organizationId]];
        if ($formId !== null) {
            $scopes[] = ['form', $formId];
            $app = DB::table('forms')->where('id', $formId)->value('application_id');
            if ($app !== null) {
                $scopes[] = ['application', (int) $app];
            }
        }
        foreach ($scopes as [$scope, $id]) {
            $quota = DB::table('storage_quotas')->where('scope_type', $scope)->where('scope_id', $id)->first(['limit_bytes']);
            if ($quota !== null && $this->usage($scope, $id) + $bytes > (int) $quota->limit_bytes) {
                throw ValidationException::withMessages(['file' => __('retention.quota_exceeded')]);
            }
        }
    }

    /** Measures every quota and records its state; a quota entering warning or exceeded is audited. Returns how many changed state. */
    public function measure(): int
    {
        $changed = 0;
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        foreach (DB::table('storage_quotas')->orderBy('id')->get() as $q) {
            $used = $this->usage((string) $q->scope_type, (int) $q->scope_id);
            $state = $used >= (int) $q->limit_bytes ? 'exceeded' : ($used >= (int) $q->warn_bytes ? 'warning' : 'ok');
            DB::table('storage_quotas')->where('id', $q->id)->update(['used_bytes' => $used, 'state' => $state, 'measured_at' => $now]);
            if ($state !== $q->state) {
                $changed++;
                if ($state !== 'ok') {
                    $this->audit->record('storage.quota_'.$state, 'operations', null, 'storage_quota', (int) $q->id,
                        ['scope' => $q->scope_type, 'used_bytes' => $used, 'warn_bytes' => (int) $q->warn_bytes, 'limit_bytes' => (int) $q->limit_bytes]);
                }
            }
        }

        return $changed;
    }

    /**
     * Storage used by form and by user (who uploaded), largest first.
     *
     * @return array{forms: list<array<string, mixed>>, users: list<array<string, mixed>>, total: int}
     */
    public function report(int $limit = 100): array
    {
        $live = static fn () => DB::table('files')->whereNull('files.deleted_at')->where('files.is_temporary', false);
        $forms = $live()->join('forms', 'forms.id', '=', 'files.form_id')
            ->groupBy('forms.id', 'forms.uuid', 'forms.key')->select(['forms.id as id', 'forms.uuid as uuid', 'forms.key as form_key'])->selectRaw('count(*) as file_count, sum(files.size_bytes) as bytes')
            ->orderByDesc('bytes')->limit($limit)->get();
        $users = $live()->join('users', 'users.id', '=', 'files.uploaded_by')
            ->groupBy('users.id', 'users.uuid', 'users.name')->select(['users.uuid as uuid', 'users.name as name'])->selectRaw('count(*) as file_count, sum(files.size_bytes) as bytes')
            ->orderByDesc('bytes')->limit($limit)->get();

        return [
            'forms' => $forms->map(static fn ($r) => ['id' => (int) $r->id, 'uuid' => strtolower((string) $r->uuid), 'key' => $r->form_key, 'files' => (int) $r->file_count, 'bytes' => (int) $r->bytes])->values()->all(),
            'users' => $users->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'name' => $r->name, 'files' => (int) $r->file_count, 'bytes' => (int) $r->bytes])->values()->all(),
            'total' => (int) $live()->sum('files.size_bytes'),
        ];
    }
}
