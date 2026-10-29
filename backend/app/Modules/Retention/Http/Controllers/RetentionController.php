<?php

declare(strict_types=1);

namespace App\Modules\Retention\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Operations\Health;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Retention\Models\RetentionPolicy;
use App\Modules\Retention\Partitions;
use App\Modules\Retention\RetentionEngine;
use App\Modules\Retention\StorageQuotas;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Retention (specification §4.26): policies and their runs, the archive and
 * restores, log partitions and table growth, and storage quotas with the
 * usage report. Everything needs "Manage retention".
 */
final class RetentionController extends Controller
{
    public function __construct(
        private readonly RetentionEngine $engine,
        private readonly ScheduledTasks $scheduler,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    /** What the policy editor offers. */
    public function options(): JsonResponse
    {
        Gate::authorize('system.manage_retention');

        return response()->json(['data' => [
            'data_classes' => RetentionEngine::DATA_CLASSES, 'end_actions' => RetentionEngine::END_ACTIONS, 'date_bases' => RetentionEngine::DATE_BASES,
            'archive_disks' => RetentionEngine::ARCHIVE_DISKS,
            'forms' => Form::query()->whereNotNull('current_version_id')->orderBy('key')->get()
                ->map(fn (Form $f) => ['uuid' => strtolower($f->uuid), 'label' => $f->translate('name') ?? Translator::humanize($f->key)])->values()->all(),
            'applications' => Application::query()->orderBy('key')->get()
                ->map(fn (Application $a) => ['uuid' => strtolower($a->uuid), 'label' => $a->translate('name') ?? Translator::humanize($a->key)])->values()->all(),
        ]]);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_retention');

        return response()->json(['data' => RetentionPolicy::query()->orderBy('id')->get()->map(fn (RetentionPolicy $p) => $this->present($p))->values()->all()]);
    }

    public function show(string $policy): JsonResponse
    {
        Gate::authorize('system.manage_retention');

        return response()->json(['data' => $this->present($this->find($policy))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $data = $this->normalize((array) $request->input('policy', []));
        $p = new RetentionPolicy;
        DB::transaction(function () use ($p, $data): void {
            $p->forceFill($data['row'])->save();
            $p->setTranslations('name', $data['name']);
            $this->schedule($p, $data['schedule'], $this->translator->labelOf($data['name'], 'retention'));
        });
        $this->audit->record('retention.policy_created', 'config', null, 'retention_policy', $p->id, $this->auditMeta($p), null, null, $p->form_id);

        return response()->json(['data' => $this->present($p->refresh())], 201);
    }

    public function update(Request $request, string $policy): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $p = $this->find($policy);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->present($p)['hash'], (string) $request->input('base_hash')), 409, __('retention.changed_meanwhile'));
        $data = $this->normalize((array) $request->input('policy', []));
        DB::transaction(function () use ($p, $data): void {
            $p->forceFill($data['row'])->save();
            $p->setTranslations('name', $data['name']);
            $this->schedule($p, $data['schedule'], $this->translator->labelOf($data['name'], 'retention'));
        });
        $this->audit->record('retention.policy_updated', 'config', null, 'retention_policy', $p->id, $this->auditMeta($p), null, null, $p->form_id);

        return response()->json(['data' => $this->present($p->refresh())]);
    }

    /** A policy that has run is kept for its history: it can be switched off, not deleted. */
    public function destroy(string $policy): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $p = $this->find($policy);
        abort_if(DB::table('retention_runs')->where('retention_policy_id', $p->id)->exists(), 422, __('retention.has_runs'));
        DB::transaction(function () use ($p): void {
            $task = $p->scheduled_task_id;
            $this->audit->record('retention.policy_deleted', 'config', null, 'retention_policy', $p->id, $this->auditMeta($p), null, null, $p->form_id);
            $p->delete();
            if ($task !== null) {
                DB::table('scheduled_tasks')->where('id', $task)->delete();
            }
        });

        return response()->json(null, 204);
    }

    public function run(string $policy): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $p = $this->find($policy);
        try {
            $this->engine->start($p, (int) Auth::id());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['policy' => $e->getMessage()]);
        }

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function runs(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $q = DB::table('retention_runs')->orderByDesc('id')->limit(100);
        if (is_string($uuid = $request->query('policy')) && Str::isUuid($uuid)) {
            $q->where('retention_policy_id', $this->find($uuid)->id);
        }
        $policies = RetentionPolicy::query()->get()->keyBy('id');

        return response()->json(['data' => $q->get()->map(function ($r) use ($policies) {
            $p = $policies[$r->retention_policy_id] ?? null;

            return [
                'uuid' => strtolower((string) $r->uuid), 'policy' => $p === null ? null : ['uuid' => strtolower($p->uuid), 'name' => $this->translator->labelOf($p->translationsFor('name'), $p->data_class), 'data_class' => $p->data_class],
                'status' => $r->status, 'scanned' => (int) $r->scanned_count, 'archived' => (int) $r->archived_count, 'deleted' => (int) $r->deleted_count,
                'skipped_legal_hold' => (int) $r->skipped_legal_hold, 'error' => $r->error, 'has_export' => $r->export_file_id !== null,
                'started_at' => self::iso($r->started_at), 'finished_at' => self::iso($r->finished_at),
                'triggered_by' => $r->triggered_by === null ? null : DB::table('users')->where('id', $r->triggered_by)->value('name'),
            ];
        })->values()->all()]);
    }

    /** The JSON Lines export of an "export then delete" run. */
    public function runExport(string $run): StreamedResponse
    {
        Gate::authorize('system.manage_retention');
        $fileId = DB::table('retention_runs')->where('uuid', strtolower($run))->value('export_file_id');
        $file = $fileId === null ? null : StoredFile::query()->whereKey((int) $fileId)->whereNull('deleted_at')->first();
        abort_if($file === null, 404);
        $this->audit->record('retention.export_downloaded', 'export', null, 'file', $file->id, ['run' => strtolower($run)]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => 'application/x-ndjson', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Archived records and log copies, newest first. */
    public function archive(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $data = $request->validate(['source' => ['sometimes', 'nullable', 'string', 'max:60'], 'form' => ['sometimes', 'nullable', 'uuid'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = DB::table('archived_records')->orderByDesc('id');
        if (($data['source'] ?? null) !== null) {
            $q->where('source_table', $data['source']);
        }
        if (($data['form'] ?? null) !== null) {
            $q->where('form_id', Form::query()->where('uuid', strtolower($data['form']))->value('id') ?? 0);
        }
        $page = $q->paginate(50);
        $forms = Form::query()->whereIn('id', collect($page->items())->pluck('form_id')->filter()->all() ?: [0])->get()->keyBy('id');

        return response()->json(['data' => collect($page->items())->map(fn ($a) => [
            'id' => (int) $a->id, 'source' => $a->source_table, 'record_id' => (int) $a->record_id,
            'form' => isset($forms[$a->form_id]) ? ['uuid' => strtolower($forms[$a->form_id]->uuid), 'label' => $forms[$a->form_id]->translate('name') ?? Translator::humanize($forms[$a->form_id]->key)] : null,
            'archived_at' => self::iso($a->archived_at), 'restored_at' => self::iso($a->restored_at),
            'restorable' => $a->form_id !== null && $a->restored_at === null && ! in_array($a->source_table, ['files', 'audit_logs'], true),
        ])->values()->all(), 'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $a = DB::table('archived_records')->where('id', $id)->where('organization_id', app(TenantContext::class)->organizationId())->first();
        abort_if($a === null, 404);
        try {
            $this->engine->restore($a, (int) Auth::id());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['archive' => $e->getMessage()]);
        }

        return response()->json(['data' => ['restored' => true]]);
    }

    /** Log partitions, the largest tables and the storage volume (log growth, §4.26). */
    public function storage(Partitions $partitions, DatabaseDriver $driver, Health $health): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $sizes = [];
        foreach (array_slice($driver->tableSizes(), 0, 20, true) as $table => $bytes) {
            $sizes[] = ['table' => $table, 'bytes' => $bytes];
        }

        return response()->json(['data' => [
            'partitions' => array_map(static fn (string $t) => ['table' => $t, 'months' => $partitions->describe($t)], Partitions::TABLES),
            'tables' => $sizes,
            'volume' => $health->storage(),
        ]]);
    }

    public function quotas(): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $rows = $this->quotaRows();

        return response()->json(['data' => ['quotas' => $rows, 'hash' => DefinitionCompiler::hash(array_map(static fn ($q) => array_diff_key($q, ['used_bytes' => 1, 'state' => 1, 'measured_at' => 1]), $rows))]]);
    }

    /** Replaces the quota list (with its hash, like every configuration document). */
    public function saveQuotas(Request $request, StorageQuotas $quotas): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $request->validate(['base_hash' => ['required', 'string', 'size:64'], 'quotas' => ['present', 'array', 'max:500']]);
        $current = $this->quotaRows();
        $hash = DefinitionCompiler::hash(array_map(static fn ($q) => array_diff_key($q, ['used_bytes' => 1, 'state' => 1, 'measured_at' => 1]), $current));
        abort_unless(hash_equals($hash, (string) $request->input('base_hash')), 409, __('retention.changed_meanwhile'));
        $errors = [];
        $clean = [];
        $seen = [];
        foreach (array_values((array) $request->input('quotas')) as $i => $q) {
            $scope = (string) ($q['scope'] ?? '');
            $id = match ($scope) {
                'organization' => app(TenantContext::class)->organizationId(),
                'application' => is_string($q['target'] ?? null) ? DB::table('applications')->where('uuid', strtolower($q['target']))->value('id') : null,
                'form' => is_string($q['target'] ?? null) ? Form::query()->where('uuid', strtolower($q['target']))->value('id') : null,
                default => null,
            };
            if ($id === null) {
                $errors["quotas.{$i}.target"][] = __('retention.quota_bad_target');

                continue;
            }
            if (isset($seen["{$scope}:{$id}"])) {
                $errors["quotas.{$i}.target"][] = __('retention.quota_duplicate');

                continue;
            }
            $seen["{$scope}:{$id}"] = true;
            $warn = filter_var($q['warn_mb'] ?? null, FILTER_VALIDATE_INT);
            $limit = filter_var($q['limit_mb'] ?? null, FILTER_VALIDATE_INT);
            if ($limit === false || $limit < 1 || $limit > 100_000_000) {
                $errors["quotas.{$i}.limit_mb"][] = __('retention.quota_bad_limit');

                continue;
            }
            if ($warn === false || $warn < 1 || $warn > $limit) {
                $errors["quotas.{$i}.warn_mb"][] = __('retention.quota_bad_warn');

                continue;
            }
            $clean[] = ['scope' => $scope, 'id' => (int) $id, 'warn' => $warn * 1048576, 'limit' => $limit * 1048576];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($clean): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $keep = [];
            foreach ($clean as $q) {
                $existing = DB::table('storage_quotas')->where('scope_type', $q['scope'])->where('scope_id', $q['id'])->first(['id']);
                $row = ['warn_bytes' => $q['warn'], 'limit_bytes' => $q['limit'], 'updated_at' => $now, 'updated_by' => Auth::id()];
                if ($existing === null) {
                    $keep[] = (int) DB::table('storage_quotas')->insertGetId($row + [
                        'uuid' => (string) Str::uuid7(), 'organization_id' => app(TenantContext::class)->organizationId(), 'created_at' => $now, 'created_by' => Auth::id(),
                        'scope_type' => $q['scope'], 'scope_id' => $q['id'], 'used_bytes' => 0, 'state' => 'ok',
                    ]);
                } else {
                    DB::table('storage_quotas')->where('id', $existing->id)->update($row);
                    $keep[] = (int) $existing->id;
                }
            }
            DB::table('storage_quotas')->whereNotIn('id', $keep ?: [0])->delete();
        });
        $quotas->measure();
        $this->audit->record('storage.quotas_saved', 'config', null, null, null, ['quotas' => count($clean)]);

        return $this->quotas();
    }

    /** Storage used by form and by user. */
    public function usage(StorageQuotas $quotas): JsonResponse
    {
        Gate::authorize('system.manage_retention');
        $report = $quotas->report();
        $forms = Form::query()->whereIn('id', array_column($report['forms'], 'id') ?: [0])->get()->keyBy('id');
        $report['forms'] = array_map(static function ($r) use ($forms) {
            $f = $forms[$r['id']] ?? null;
            unset($r['id']);

            return $r + ['label' => $f === null ? $r['key'] : ($f->translate('name') ?? Translator::humanize($f->key))];
        }, $report['forms']);

        return response()->json(['data' => $report]);
    }

    // ---------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function quotaRows(): array
    {
        $forms = Form::query()->get(['id', 'uuid', 'key'])->keyBy('id');
        $apps = Application::query()->get()->keyBy('id');

        return DB::table('storage_quotas')->orderBy('id')->get()->map(function ($q) use ($forms, $apps) {
            $target = null;
            $label = null;
            if ($q->scope_type === 'form' && isset($forms[$q->scope_id])) {
                $target = strtolower($forms[$q->scope_id]->uuid);
                $label = $forms[$q->scope_id]->translate('name') ?? Translator::humanize($forms[$q->scope_id]->key);
            } elseif ($q->scope_type === 'application' && isset($apps[$q->scope_id])) {
                $target = strtolower((string) $apps[$q->scope_id]->uuid);
                $label = $apps[$q->scope_id]->translate('name') ?? Translator::humanize($apps[$q->scope_id]->key);
            }

            return [
                'uuid' => strtolower((string) $q->uuid), 'scope' => $q->scope_type, 'target' => $target, 'label' => $label,
                'warn_mb' => intdiv((int) $q->warn_bytes, 1048576), 'limit_mb' => intdiv((int) $q->limit_bytes, 1048576),
                'used_bytes' => (int) $q->used_bytes, 'state' => $q->state, 'measured_at' => self::iso($q->measured_at),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{row: array<string, mixed>, name: array<string, string>, schedule: array{cron: string, timezone: string}}
     */
    private function normalize(array $d): array
    {
        $errors = [];
        $name = [];
        foreach ((array) ($d['name'] ?? []) as $loc => $v) {
            if (is_string($loc) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $loc) === 1 && is_string($v) && trim($v) !== '') {
                $name[$loc] = mb_substr(trim($v), 0, 255);
            }
        }
        if ($name === []) {
            $errors['name'][] = __('retention.name_required');
        }
        $class = (string) ($d['data_class'] ?? '');
        if (! in_array($class, RetentionEngine::DATA_CLASSES, true)) {
            $errors['data_class'][] = __('retention.bad_data_class');
        }
        $action = (string) ($d['end_action'] ?? '');
        if (! in_array($action, RetentionEngine::END_ACTIONS, true)) {
            $errors['end_action'][] = __('retention.bad_end_action');
        } elseif ($class === 'audit_logs' && $action !== 'archive') {
            // Audit entries are immutable: their policy only ever makes a verified copy.
            $errors['end_action'][] = __('retention.audit_archive_only');
        }
        $basis = (string) ($d['date_basis'] ?? 'created_at');
        if (! in_array($basis, RetentionEngine::DATE_BASES, true) || ($basis !== 'created_at' && $class !== 'records')) {
            $errors['date_basis'][] = __('retention.bad_date_basis');
        }
        $days = filter_var($d['retention_days'] ?? null, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 36500) {
            $errors['retention_days'][] = __('retention.bad_days');
        }
        $disk = ($d['archive_disk'] ?? null) === null || $d['archive_disk'] === '' ? 'archive' : (string) $d['archive_disk'];
        if (! in_array($disk, RetentionEngine::ARCHIVE_DISKS, true)) {
            $errors['archive_disk'][] = __('retention.bad_disk');
        }
        $formId = null;
        $appId = null;
        if (in_array($class, ['records', 'recycle_bin', 'attachments'], true)) {
            if (is_string($d['form'] ?? null) && $d['form'] !== '') {
                $formId = Form::query()->where('uuid', strtolower($d['form']))->whereNotNull('current_version_id')->value('id');
                if ($formId === null) {
                    $errors['form'][] = __('retention.bad_form');
                }
            } elseif (is_string($d['application'] ?? null) && $d['application'] !== '') {
                $appId = DB::table('applications')->where('uuid', strtolower($d['application']))->value('id');
                if ($appId === null) {
                    $errors['application'][] = __('retention.bad_application');
                }
            }
        }
        $cron = trim((string) ($d['schedule']['cron'] ?? ''));
        $tz = (string) ($d['schedule']['timezone'] ?? 'UTC');
        if ($cron === '' || ! ScheduledTasks::valid($cron, $tz)) {
            $errors['schedule.cron'][] = __('retention.bad_cron');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'row' => [
                'data_class' => $class, 'end_action' => $action, 'date_basis' => $basis, 'retention_days' => (int) $days, 'archive_disk' => $action === 'archive' ? $disk : null,
                'form_id' => $formId, 'application_id' => $appId, 'is_active' => (bool) ($d['active'] ?? true),
            ],
            'name' => $name,
            'schedule' => ['cron' => $cron, 'timezone' => $tz],
        ];
    }

    /** @param array{cron: string, timezone: string} $schedule */
    private function schedule(RetentionPolicy $p, array $schedule, string $label): void
    {
        $id = $this->scheduler->put($p->scheduled_task_id, 'retention', 'retention_policy', $p->id, mb_substr($label, 0, 255), $schedule['cron'], $schedule['timezone'], $p->is_active);
        if ($id !== $p->scheduled_task_id) {
            $p->forceFill(['scheduled_task_id' => $id])->save();
        }
    }

    /** @return array<string, mixed> */
    private function present(RetentionPolicy $p): array
    {
        $task = $p->scheduled_task_id === null ? null : DB::table('scheduled_tasks')->where('id', $p->scheduled_task_id)->first(['cron_expression', 'timezone', 'next_run_at']);
        $form = $p->form_id === null ? null : Form::query()->find($p->form_id);
        $app = $p->application_id === null ? null : DB::table('applications')->where('id', $p->application_id)->first(['uuid']);
        $last = DB::table('retention_runs')->where('retention_policy_id', $p->id)->orderByDesc('id')->first(['status', 'started_at', 'finished_at']);
        $doc = [
            'uuid' => strtolower($p->uuid), 'name' => (object) $p->translationsFor('name'), 'data_class' => $p->data_class,
            'form' => $form === null ? null : strtolower($form->uuid), 'application' => $app === null ? null : strtolower((string) $app->uuid),
            'retention_days' => $p->retention_days, 'date_basis' => $p->date_basis, 'end_action' => $p->end_action, 'archive_disk' => $p->archive_disk,
            'schedule' => $task === null ? null : ['cron' => $task->cron_expression, 'timezone' => $task->timezone], 'active' => $p->is_active,
        ];

        return $doc + [
            'hash' => DefinitionCompiler::hash($doc),
            'label' => $this->translator->labelOf($p->translationsFor('name'), $p->data_class),
            'next_run_at' => self::iso($task?->next_run_at),
            'last_run' => $last === null ? null : ['status' => $last->status, 'started_at' => self::iso($last->started_at), 'finished_at' => self::iso($last->finished_at)],
        ];
    }

    /** @return array<string, mixed> */
    private function auditMeta(RetentionPolicy $p): array
    {
        return ['data_class' => $p->data_class, 'end_action' => $p->end_action, 'retention_days' => $p->retention_days, 'active' => $p->is_active];
    }

    private function find(string $uuid): RetentionPolicy
    {
        $p = Str::isUuid($uuid) ? RetentionPolicy::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($p === null, 404);

        return $p;
    }

    private static function iso(mixed $v): ?string
    {
        return $v === null ? null : Carbon::parse((string) $v, 'UTC')->toIso8601ZuluString();
    }
}
