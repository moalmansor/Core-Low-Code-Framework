<?php

declare(strict_types=1);

namespace App\Modules\Forms\Publishing;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessCache;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormVersion;
use App\Modules\Forms\Models\Relation;
use App\Modules\Schema\Execution\PublishLocks;
use App\Modules\Schema\Execution\SchemaExecutor;
use App\Modules\Schema\Execution\SchemaReconciler;
use App\Modules\Schema\Execution\Snapshots;
use App\Modules\Schema\Models\MigrationPlan;
use App\Modules\Schema\Planning\MigrationPlanner;
use App\Modules\Schema\Planning\SchemaDiffer;
use App\Modules\Workflow\StatusMappings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Draft → impact analysis → publish (specification §4.9–§4.10, architecture
 * §12–§13). `prepare()` compiles the draft and computes the plan and impact
 * without changing anything; `start()` persists the plan the admin confirmed;
 * `execute()` (queued) takes the locks and snapshots, runs the plan, and only
 * after every step applied switches the form to the new version in one
 * metadata transaction.
 */
final class PublishService
{
    public function __construct(
        private readonly DefinitionCompiler $compiler,
        private readonly PublishedDefinitions $definitions,
        private readonly SchemaDiffer $differ,
        private readonly MigrationPlanner $planner,
        private readonly ImpactAnalyzer $impact,
        private readonly VersionDiff $versionDiff,
        private readonly SchemaExecutor $executor,
        private readonly PublishLocks $locks,
        private readonly Snapshots $snapshots,
        private readonly SchemaReconciler $reconciler,
        private readonly AuditWriter $audit,
        private readonly OutboxWriter $outbox,
        private readonly AccessCache $accessCache,
        private readonly CorrelationId $correlation,
        private readonly DatabaseDriver $driver,
        private readonly Placement $placement,
        private readonly StatusMappings $statusMappings,
    ) {}

    /**
     * Compiles the draft and computes the diff, plan and impact.
     *
     * @return array{definition: array<string, mixed>, operations: list<array<string, mixed>>, steps: list<array<string, mixed>>, impact: array<string, mixed>, diff: array<string, mixed>, hash: string, stamp: string, published: array<string, mixed>|null}
     */
    public function prepare(Form $form, ?array $replacementDefinition = null): array
    {
        if ($form->state === 'schema_inconsistent') {
            throw new PublishBlocked('schema_inconsistent', 'The form is schema-inconsistent. Repair it before publishing.');
        }
        $published = $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
        $nextVersion = (int) $form->draft_version_number;
        $compiled = $this->compiler->compile($form, $nextVersion);
        $definition = $compiled['definition'];
        // Deterministic per version, so the reviewed plan and the executed plan are identical.
        $stamp = 'v'.$nextVersion;
        $live = [];
        if ($form->binding_mode === 'bound' && $published === null && in_array($form->table_name, $this->driver->tables(), true)) {
            $live[$form->table_name] = array_column($this->driver->columns($form->table_name), 'name');
        }
        $operations = $this->differ->diff($published['schema'] ?? null, $definition['schema'], $stamp, $live);
        // Records move between statuses only after the schema steps (architecture §13.5).
        $mapping = $this->statusMappings->plan($form, $definition, $published);
        $operations = [...$operations, ...$mapping['operations']];
        $steps = $this->planner->plan($operations, $stamp);
        $diff = $this->versionDiff->diff($published, $definition);
        $impact = $this->impact->analyze($form, $definition, $published, $operations, $steps, $compiled['problems'], $diff);
        $impact['workflow'] = ['removed_statuses' => $mapping['summary']['removed'], 'unassigned_records' => $mapping['summary']['unassigned']];
        $impact['blocking'] = [...$impact['blocking'], ...$mapping['blocking']];
        $impact['lock_set'] = array_map(static fn (Form $f) => ['uuid' => $f->uuid, 'key' => $f->key], Form::query()->whereIn('id', $this->lockSet($form, $definition))->get(['uuid', 'key'])->all());
        $hash = hash('sha256', DefinitionCompiler::hash($definition['schema']).DefinitionCompiler::hash(array_map(static fn ($s) => [$s['operation'], $s['table_name'], $s['forward']], $steps)).DefinitionCompiler::hash($impact['blocking']));

        return ['definition' => $definition, 'operations' => $operations, 'steps' => $steps, 'impact' => $impact, 'diff' => $diff, 'hash' => $hash, 'stamp' => $stamp, 'published' => $published];
    }

    /**
     * Persists the confirmed plan and returns it (execution is queued).
     *
     * @param  array{impact_hash: string, confirm_blocking?: bool, confirm_destructive?: bool, typed_confirmation?: string|null, change_note?: string|null, purpose?: string, rollback_of?: int|null}  $options
     */
    public function start(Form $form, int $userId, array $options): MigrationPlan
    {
        $prepared = $this->prepare($form);
        if (! hash_equals($prepared['hash'], $options['impact_hash'])) {
            throw new PublishBlocked('impact_changed', 'The draft or the database changed since you reviewed the impact. Review it again.');
        }
        $impact = $prepared['impact'];
        if ($impact['blocking'] !== []) {
            throw new PublishBlocked('blocking_issues', 'Resolve the blocking issues listed in the impact analysis first.');
        }
        if ($impact['requires_confirmation']['blocking_steps'] && ! ($options['confirm_blocking'] ?? false)) {
            throw new PublishBlocked('confirmation_required', 'Some steps lock large tables; confirm that they may run now.');
        }
        if ($impact['requires_confirmation']['failing_required'] && ! ($options['confirm_blocking'] ?? false)) {
            throw new PublishBlocked('confirmation_required', 'Existing records do not satisfy new required fields; confirm to continue.');
        }
        if ($impact['requires_confirmation']['destructive'] && (! ($options['confirm_destructive'] ?? false) || ($options['typed_confirmation'] ?? null) !== $form->key)) {
            throw new PublishBlocked('typed_confirmation_required', 'This change is not fully reversible. Type the form key to confirm.');
        }
        $running = MigrationPlan::query()->where('form_id', $form->id)->whereIn('status', ['pending', 'locked', 'running', 'reversing'])->first();
        if ($running !== null) {
            throw new PublishBlocked('publish_in_progress', 'A publish of this form is already in progress.');
        }

        return DB::transaction(function () use ($form, $userId, $options, $prepared): MigrationPlan {
            $plan = MigrationPlan::query()->create([
                'form_id' => $form->id,
                'from_version_id' => $form->current_version_id,
                'to_version_number' => (int) $form->draft_version_number,
                'purpose' => $options['purpose'] ?? 'publish',
                'status' => 'pending',
                'steps_total' => count($prepared['steps']),
                'steps_applied' => 0,
                'impact' => $prepared['impact'] + ['_definition' => $prepared['definition'], '_diff' => $prepared['diff'], '_options' => array_intersect_key($options, array_flip(['change_note', 'rollback_of', 'placement']))],
                'confirmed_by' => $userId,
                'confirmed_at' => Carbon::now('UTC'),
                'correlation_id' => $this->correlation->get(),
            ]);
            foreach ($prepared['steps'] as $s) {
                $plan->steps()->create(array_intersect_key($s, array_flip(['sequence', 'operation', 'table_name', 'forward', 'reverse', 'sql_preview', 'is_destructive', 'is_online', 'estimated_ms'])) + ['status' => 'pending']);
            }
            $this->audit->record('form.publish_requested', 'schema', null, 'form', $form->id, ['plan' => $plan->uuid, 'steps' => count($prepared['steps']), 'change_class' => $prepared['impact']['schema']['change_class']], $userId);

            return $plan;
        });
    }

    /**
     * Runs a persisted plan (queued job). Returns `waiting` when another publish
     * holds a related form; the job retries later.
     */
    public function execute(MigrationPlan $plan): string
    {
        $form = Form::query()->findOrFail($plan->form_id);
        $definition = $plan->impact['_definition'];
        $lockSet = $this->lockSet($form, $definition);
        $lock = $this->locks->acquire($lockSet, (int) $plan->confirmed_by, $plan->id);
        if (! $lock['acquired']) {
            return 'waiting';
        }
        $group = $lock['token'];
        try {
            $plan->forceFill(['status' => 'locked', 'lock_token' => $group])->save();
            $named = 'publish-'.$form->id;
            $this->driver->namedLock($named, 30);
            try {
                $published = $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
                $affected = array_values(array_unique(array_filter(
                    $plan->steps()->pluck('table_name')->all(),
                    fn (string $t): bool => in_array(strtolower($t), array_map('strtolower', $this->driver->tables()), true),
                )));
                $withData = $plan->steps()->where('is_destructive', true)->exists();
                $snaps = $this->snapshots->take($form->id, $plan->id, $published, $affected, $withData);
                $plan->forceFill(['snapshot_id' => ($snaps['data'] ?? $snaps['metadata'])->id])->save();
                $this->locks->heartbeat($group);
                $status = $this->executor->run($plan);
                if ($status === 'applied') {
                    $version = $this->switchVersion($form, $plan, $definition, $published);
                    $this->placement->apply($form->refresh(), $plan->impact['_options']['placement'] ?? [], (int) $plan->confirmed_by);
                    $this->reconciler->run($form->refresh(), 'post_publish', (int) $plan->confirmed_by);
                    $this->outbox->publish('form.published', ['form' => $form->uuid, 'version' => $version->version_number]);
                } elseif ($status === 'inconsistent') {
                    $form->forceFill(['state' => 'schema_inconsistent'])->saveQuietly();
                    $this->audit->record('form.schema_inconsistent', 'schema', null, 'form', $form->id, ['plan' => $plan->uuid], (int) $plan->confirmed_by);
                    $this->reconciler->run($form, 'post_failure', (int) $plan->confirmed_by);
                } else {
                    $this->audit->record('form.publish_reversed', 'schema', null, 'form', $form->id, ['plan' => $plan->uuid, 'error' => $plan->refresh()->error], (int) $plan->confirmed_by);
                }

                return $status;
            } finally {
                $this->driver->releaseNamedLock($named);
            }
        } finally {
            $this->locks->release($group);
        }
    }

    /** Completes a plan whose remaining steps were applied from the repair screen. */
    public function finishRepaired(MigrationPlan $plan): void
    {
        $form = Form::query()->findOrFail($plan->form_id);
        if ($plan->status !== 'applied' || FormVersion::query()->where('migration_plan_id', $plan->id)->exists()) {
            return;
        }
        $published = $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
        $this->switchVersion($form, $plan, $plan->impact['_definition'], $published);
        $this->placement->apply($form->refresh(), $plan->impact['_options']['placement'] ?? [], (int) $plan->confirmed_by);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>|null  $published
     */
    private function switchVersion(Form $form, MigrationPlan $plan, array $definition, ?array $published): FormVersion
    {
        return DB::transaction(function () use ($form, $plan, $definition, $published): FormVersion {
            $options = $plan->impact['_options'] ?? [];
            $diff = $plan->impact['_diff'] ?? $this->versionDiff->diff($published, $definition);
            FormVersion::query()->where('form_id', $form->id)->where('state', 'published')->update(['state' => 'superseded']);
            if (($options['rollback_of'] ?? null) !== null) {
                FormVersion::query()->whereKey($options['rollback_of'])->update(['state' => 'rolled_back']);
            }
            $impact = $plan->impact;
            unset($impact['_definition'], $impact['_diff'], $impact['_options']);
            $version = FormVersion::query()->create([
                'form_id' => $form->id,
                'version_number' => $plan->to_version_number,
                'state' => 'published',
                'definition' => $definition,
                'definition_hash' => DefinitionCompiler::hash($definition),
                'schema_hash' => DefinitionCompiler::hash($definition['schema']),
                'change_class' => MigrationPlanner::changeClass($plan->steps()->get()->map(static fn ($s) => $s->forward)->all()),
                'diff_from_previous' => $diff,
                'impact_report' => $impact,
                'migration_plan_id' => $plan->id,
                'snapshot_id' => $plan->snapshot_id,
                'rollback_of_version_id' => $options['rollback_of'] ?? null,
                'published_at' => Carbon::now('UTC'),
                'published_by' => $plan->confirmed_by,
                'change_note' => $options['change_note'] ?? null,
            ]);
            // Field removals become permanent archives of their columns.
            foreach ($plan->steps()->where('operation', 'archive_column')->get() as $step) {
                DB::table('fields')->where('form_id', $form->id)->whereNotNull('archived_at')->where('column_name', $step->forward['column'])
                    ->update(['archived_column_name' => $step->forward['archived']]);
            }
            $this->statusMappings->finalize($form, $version->id, $plan->id, $definition, $published, $plan->confirmed_by === null ? null : (int) $plan->confirmed_by);
            $form->forceFill([
                'workflow_enabled' => ($definition['workflow']['statuses'] ?? []) !== [],
                'current_version_id' => $version->id,
                'state' => $form->state === 'draft' || $form->state === 'schema_inconsistent' ? 'published' : $form->state,
                'draft_version_number' => $version->version_number + 1,
            ])->saveQuietly();
            $this->audit->record('form.published', 'schema', [['field_key' => 'version', 'old' => $published['form']['version'] ?? null, 'new' => $version->version_number]], 'form', $form->id, ['plan' => $plan->uuid, 'diff' => $diff['summary'] ?? [], 'change_class' => $version->change_class], (int) $plan->confirmed_by);
            Cache::forget('def:current:'.$form->id);
            $this->definitions->forget($form->uuid);
            $this->accessCache->bump();

            return $version;
        });
    }

    /**
     * The form and every form related to it (architecture §12.4).
     *
     * @param  array<string, mixed>  $definition
     * @return list<int>
     */
    public function lockSet(Form $form, array $definition): array
    {
        $uuids = array_keys($definition['targets'] ?? []);
        $ids = Form::query()->whereIn('uuid', $uuids)->pluck('id')->all();
        $ids = [...$ids, ...Relation::query()->where('target_form_id', $form->id)->pluck('source_form_id')->all(), $form->id];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
