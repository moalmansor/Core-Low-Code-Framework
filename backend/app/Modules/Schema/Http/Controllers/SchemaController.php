<?php

declare(strict_types=1);

namespace App\Modules\Schema\Http\Controllers;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\FrameworkTables;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\Introspection;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\FormTables;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Publishing\PublishService;
use App\Modules\Schema\Execution\SchemaExecutor;
use App\Modules\Schema\Execution\SchemaReconciler;
use App\Modules\Schema\Execution\Snapshots;
use App\Modules\Schema\Models\MigrationPlan;
use App\Modules\Schema\Models\PublishLock;
use App\Modules\Schema\Models\SchemaReconciliationReport;
use App\Modules\Schema\Models\SchemaSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Schema explorer with ERD (specification §4.8), migration plans and the
 * repair screen (§4.9), and reconciliation reports. Framework metadata
 * tables are never listed: the explorer shows form, collection, child and
 * pivot tables, and — for binding — other existing tables.
 */
final class SchemaController extends Controller
{
    public function __construct(
        private readonly DatabaseDriver $driver,
        private readonly PublishedDefinitions $definitions,
        private readonly Translator $translator,
    ) {}

    /** Every record table known to metadata, with its owning form. */
    public function tables(): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $forms = Form::query()->whereNotNull('current_version_id')->get();
        $names = $this->translator->many('form', $forms->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name']);
        $live = array_flip(array_map('strtolower', $this->driver->tables()));
        $out = [];
        foreach ($forms as $form) {
            $def = $this->definitions->version($form->id, (int) $form->current_version_id);
            foreach ($def['schema']['tables'] ?? [] as $t) {
                $out[] = [
                    'name' => $t['name'], 'role' => $t['role'], 'form' => $form->uuid, 'form_key' => $form->key,
                    'form_name' => $names[$form->id]['name'] ?? Translator::humanize((string) $form->key), 'kind' => $form->kind,
                    'columns' => count($t['columns']), 'exists' => isset($live[strtolower($t['name'])]),
                    'rows' => isset($live[strtolower($t['name'])]) ? $this->driver->tableStats($t['name'])->rows : null,
                ];
            }
        }

        return response()->json(['data' => $out]);
    }

    /** Columns, indexes and foreign keys of one table: metadata expectation side by side with the live engine. */
    public function table(string $name, FormTables $tables): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        abort_if(FrameworkTables::contains($name), 404);
        abort_unless(in_array(strtolower($name), array_map('strtolower', $this->driver->tables()), true), 404);
        $expected = null;
        $owner = null;
        foreach (Form::query()->whereNotNull('current_version_id')->get() as $form) {
            foreach ($this->definitions->version($form->id, (int) $form->current_version_id)['schema']['tables'] ?? [] as $t) {
                if (strtolower($t['name']) === strtolower($name)) {
                    $expected = $t;
                    $owner = $form;
                }
            }
        }
        abort_if($expected === null && ! in_array($name, $tables->bindableTables(), true), 404);
        $fieldKeys = [];
        if ($owner !== null) {
            foreach ($this->definitions->version($owner->id, (int) $owner->current_version_id)['fields'] ?? [] as $f) {
                $fieldKeys[$f['uuid']] = $f['key'];
            }
        }

        return response()->json(['data' => [
            'name' => $name,
            'form' => $owner?->uuid,
            'columns' => array_map(static function (array $c) use ($expected, $fieldKeys): array {
                $exp = collect($expected['columns'] ?? [])->firstWhere('name', $c['name']);

                return $c + ['field' => isset($exp['field']) ? ($fieldKeys[$exp['field']] ?? null) : null, 'system' => (bool) ($exp['system'] ?? false), 'archived' => str_starts_with($c['name'], 'zz_')];
            }, $this->driver->columns($name)),
            'indexes' => $this->driver->indexes($name),
            'foreign_keys' => $this->driver->foreignKeys($name),
            'rows' => $this->driver->tableStats($name)->rows,
        ]]);
    }

    /** Existing tables a bound form may use (introspection, specification §4.6 database binding). */
    public function bindable(FormTables $tables): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => array_map(fn (string $t): array => [
            'name' => $t,
            'columns' => array_map(static fn (array $c) => ['name' => $c['name'], 'type' => $c['type'], 'nullable' => $c['nullable'], 'logical' => Introspection::logicalType($c['type'])], $this->driver->columns($t)),
        ], $tables->bindableTables())]);
    }

    /** Entity-relationship graph of the published forms (or a selection). */
    public function erd(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['forms' => ['sometimes', 'array'], 'forms.*' => ['uuid']]);
        $forms = Form::query()->whereNotNull('current_version_id')->when(isset($data['forms']), fn ($q) => $q->whereIn('uuid', $data['forms']))->get();
        $names = $this->translator->many('form', $forms->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name']);
        $nodes = [];
        $edges = [];
        foreach ($forms as $form) {
            $def = $this->definitions->version($form->id, (int) $form->current_version_id);
            $fieldsByUuid = array_column($def['fields'] ?? [], null, 'uuid');
            foreach ($def['schema']['tables'] ?? [] as $t) {
                $nodes[] = [
                    'id' => $t['name'], 'role' => $t['role'], 'form' => $form->uuid, 'kind' => $form->kind,
                    'label' => $t['role'] === 'main' ? ($names[$form->id]['name'] ?? Translator::humanize((string) $form->key)) : $t['name'],
                    'columns' => array_map(static fn (array $c) => [
                        'name' => $c['name'], 'type' => $c['type'], 'nullable' => $c['nullable'],
                        'field' => isset($c['field']) ? ($fieldsByUuid[$c['field']]['key'] ?? null) : null, 'system' => (bool) ($c['system'] ?? false),
                    ], $t['columns']),
                ];
                foreach ($t['foreignKeys'] as $fk) {
                    if (! FrameworkTables::contains($fk['references'])) {
                        $edges[] = ['from' => $t['name'], 'to' => $fk['references'], 'column' => $fk['column'], 'on_delete' => $fk['onDelete']];
                    }
                }
            }
            foreach ($def['schema']['external'] ?? [] as $e) {
                $edges[] = ['from' => $e['table'], 'to' => $e['foreignKey']['references'], 'column' => $e['column']['name'], 'on_delete' => $e['foreignKey']['onDelete']];
            }
        }

        return response()->json(['data' => ['nodes' => $nodes, 'edges' => $edges]]);
    }

    public function plans(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['form' => ['sometimes', 'uuid'], 'status' => ['sometimes', 'string']]);
        $q = MigrationPlan::query()->orderByDesc('id');
        if (isset($data['form'])) {
            $q->whereIn('form_id', Form::query()->where('uuid', $data['form'])->select('id'));
        }
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }
        $page = $q->paginate(25);
        $forms = Form::query()->whereIn('id', $page->getCollection()->pluck('form_id'))->pluck('key', 'id');

        return response()->json([
            'data' => $page->getCollection()->map(static fn (MigrationPlan $p) => [
                'uuid' => $p->uuid, 'form_key' => $forms[$p->form_id] ?? null, 'purpose' => $p->purpose, 'status' => $p->status,
                'to_version' => $p->to_version_number, 'steps_total' => $p->steps_total, 'steps_applied' => $p->steps_applied,
                'created_at' => $p->created_at?->toIso8601String(), 'finished_at' => $p->finished_at?->toIso8601String(), 'error' => $p->error,
            ])->values(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage()],
        ]);
    }

    public function plan(MigrationPlan $plan): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $form = Form::query()->find($plan->form_id);
        $impact = $plan->impact;
        unset($impact['_definition'], $impact['_diff'], $impact['_options']);
        $waiting = PublishLock::query()->where('migration_plan_id', $plan->id)->where('status', 'waiting')->first();
        $blocker = $waiting?->blocked_by_lock_id === null ? null : PublishLock::query()->find($waiting->blocked_by_lock_id);
        $snapshots = SchemaSnapshot::query()->where('migration_plan_id', $plan->id)->get();

        return response()->json(['data' => [
            'uuid' => $plan->uuid,
            'form' => $form === null ? null : ['uuid' => $form->uuid, 'key' => $form->key, 'state' => $form->state],
            'purpose' => $plan->purpose,
            'status' => $plan->status,
            'to_version' => $plan->to_version_number,
            'steps_total' => $plan->steps_total,
            'steps_applied' => $plan->steps_applied,
            'error' => $plan->error,
            'impact' => $impact,
            'queued_behind' => $blocker === null ? null : [
                'form' => Form::query()->whereKey($blocker->form_id)->value('key'),
                'user' => DB::table('users')->where('id', $blocker->owner_user_id)->value('name'),
                'since' => $blocker->acquired_at?->toIso8601String(),
            ],
            'steps' => $plan->steps()->get()->map(static fn ($s) => [
                'sequence' => $s->sequence, 'operation' => $s->operation, 'table' => $s->table_name, 'status' => $s->status,
                'is_destructive' => $s->is_destructive, 'is_online' => $s->is_online, 'estimated_ms' => $s->estimated_ms,
                'duration_ms' => $s->duration_ms, 'error' => $s->error, 'sql_preview' => $s->sql_preview,
            ])->values(),
            'snapshots' => $snapshots->map(static fn (SchemaSnapshot $s) => [
                'uuid' => $s->uuid, 'kind' => $s->kind, 'disk' => $s->disk, 'path' => $s->path, 'size_bytes' => $s->size_bytes,
                'tables' => $s->tables, 'expires_at' => $s->expires_at->toIso8601String(), 'restored_at' => $s->restored_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    /**
     * Repair actions on a failed or inconsistent plan (architecture §12.2):
     * retry remaining steps, retry the reverse, mark a step manually
     * reconciled (only when a reconciliation check of the table is clean), or
     * restore the pre-publish data backup.
     */
    public function repair(Request $request, MigrationPlan $plan, string $action, SchemaExecutor $executor, SchemaReconciler $reconciler, Snapshots $snapshots, AuditWriter $audit): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        Gate::authorize('system.repair_data');
        abort_unless(in_array($plan->status, ['failed', 'reversed', 'inconsistent'], true), 422, __('schema.plan_not_repairable'));
        $form = Form::query()->findOrFail($plan->form_id);
        $data = $request->validate(['step' => ['required_if:action,reconcile-step', 'integer', 'min:1']]);
        $result = match ($action) {
            'retry' => $plan->status === 'reversed' ? abort(422, __('schema.publish_again')) : $executor->retry($plan),
            'reverse' => $executor->reverse($plan),
            'reconcile-step' => $this->reconcileStep($plan, (int) $request->input('step'), $reconciler, $form),
            'restore-snapshot' => $this->restoreSnapshot($plan, $snapshots),
            default => abort(404),
        };
        if (in_array($result, ['applied', 'reversed'], true) && $form->state === 'schema_inconsistent' && ! $plan->steps()->whereIn('status', ['failed', 'reverse_failed'])->exists()) {
            $form->forceFill(['state' => $form->current_version_id === null ? 'draft' : 'published'])->saveQuietly();
            if ($result === 'applied') {
                // The plan finished: switch to the new version the normal way.
                app(PublishService::class)->finishRepaired($plan->refresh());
            }
        }
        $audit->record('schema.repair_'.str_replace('-', '_', $action), 'schema', null, 'migration_plan', $plan->id, ['result' => $result, 'step' => $data['step'] ?? null]);
        $reconciler->run($form, 'post_failure', (int) Auth::id());

        return $this->plan($plan->refresh());
    }

    public function reconcile(Request $request, SchemaReconciler $reconciler): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['form' => ['sometimes', 'nullable', 'uuid', Rule::exists('forms', 'uuid')]]);
        $form = isset($data['form']) ? Form::query()->where('uuid', $data['form'])->first() : null;
        $report = $reconciler->run($form, 'on_demand', (int) Auth::id());

        return response()->json(['data' => $this->presentReport($report)], 201);
    }

    public function reports(): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => SchemaReconciliationReport::query()->orderByDesc('id')->limit(50)->get()->map($this->presentReport(...))->values()]);
    }

    /** @return array<string, mixed> */
    private function presentReport(SchemaReconciliationReport $r): array
    {
        return [
            'id' => $r->id, 'form' => $r->form_id === null ? null : Form::query()->whereKey($r->form_id)->value('key'), 'trigger' => $r->trigger,
            'engine' => $r->engine, 'status' => $r->status, 'difference_count' => $r->difference_count, 'differences' => $r->differences,
            'started_at' => $r->started_at->toIso8601String(), 'finished_at' => $r->finished_at?->toIso8601String(),
        ];
    }

    private function reconcileStep(MigrationPlan $plan, int $sequence, SchemaReconciler $reconciler, Form $form): string
    {
        $step = $plan->steps()->where('sequence', $sequence)->firstOrFail();
        abort_unless(in_array($step->status, ['failed', 'reverse_failed'], true), 422, __('schema.step_not_failed'));
        $applied = $this->stepVisible($step->forward);
        $reversed = $this->stepVisible($step->reverse);
        abort_unless($applied || $reversed, 422, __('schema.step_not_reconciled'));
        $step->forceFill(['status' => $applied ? 'applied' : 'reversed', 'error' => 'Manually reconciled after verification.'])->save();
        $open = $plan->steps()->whereIn('status', ['failed', 'reverse_failed', 'pending'])->exists();
        if (! $open) {
            $allApplied = ! $plan->steps()->where('status', '!=', 'applied')->exists();
            $plan->forceFill(['status' => $allApplied ? 'applied' : 'reversed'])->save();

            return $allApplied ? 'applied' : 'reversed';
        }

        return $plan->status;
    }

    /** Whether a DDL step's effect is present in the live schema (proof for manual reconciliation). */
    private function stepVisible(array $spec): bool
    {
        $tables = array_map('strtolower', $this->driver->tables());
        $cols = fn (string $t): array => in_array(strtolower($t), $tables, true) ? array_map(static fn ($c) => strtolower($c['name']), $this->driver->columns($t)) : [];
        $idx = fn (string $t): array => in_array(strtolower($t), $tables, true) ? array_map(static fn ($i) => strtolower($i['name']), $this->driver->indexes($t)) : [];
        $fks = fn (string $t): array => in_array(strtolower($t), $tables, true) ? array_map(static fn ($f) => strtolower((string) $f['name']), $this->driver->foreignKeys($t)) : [];

        return match ($spec['op']) {
            'create_table', 'create_pivot' => in_array(strtolower($spec['table']), $tables, true),
            'rename_table', 'drop_table_archive', 'restore_table' => in_array(strtolower($spec['to'] ?? $spec['archived']), $tables, true) && ! in_array(strtolower($spec['from'] ?? $spec['table']), $tables, true),
            'add_column' => in_array(strtolower($spec['column']['name']), $cols($spec['table']), true),
            'rename_column' => in_array(strtolower($spec['to']), $cols($spec['table']), true) && ! in_array(strtolower($spec['from']), $cols($spec['table']), true),
            'archive_column' => in_array(strtolower($spec['archived']), $cols($spec['table']), true),
            'restore_column' => in_array(strtolower($spec['column']), $cols($spec['table']), true),
            'add_index' => in_array(strtolower($spec['index']['name']), $idx($spec['table']), true),
            'drop_index' => ! in_array(strtolower($spec['index']['name']), $idx($spec['table']), true),
            'add_foreign_key' => in_array(strtolower($spec['foreignKey']['name']), $fks($spec['table']), true),
            'drop_foreign_key' => ! in_array(strtolower($spec['foreignKey']['name']), $fks($spec['table']), true),
            'noop' => true,
            default => false,
        };
    }

    private function restoreSnapshot(MigrationPlan $plan, Snapshots $snapshots): string
    {
        $backup = SchemaSnapshot::query()->where('migration_plan_id', $plan->id)->where('kind', 'data_backup')->first();
        abort_if($backup === null, 422, __('schema.no_data_backup'));
        $snapshots->restore($backup);

        return $plan->status;
    }
}
