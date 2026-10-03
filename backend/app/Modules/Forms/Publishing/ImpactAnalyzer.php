<?php

declare(strict_types=1);

namespace App\Modules\Forms\Publishing;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\MenuItem;
use App\Modules\Forms\Models\Relation;
use App\Modules\Schema\Execution\SchemaExecutor;
use App\Modules\Schema\Planning\MigrationPlanner;
use Illuminate\Support\Facades\DB;

/**
 * Everything a publish affects (specification §4.10, architecture §13.2),
 * computed from the diff between the compiled draft and the current version.
 * Blocking issues must be resolved before the publish button is enabled.
 */
final class ImpactAnalyzer
{
    public function __construct(
        private readonly DatabaseDriver $driver,
        private readonly SchemaExecutor $executor,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $definition  compiled draft
     * @param  array<string, mixed>|null  $published  current version
     * @param  list<array<string, mixed>>  $operations
     * @param  list<array<string, mixed>>  $steps
     * @param  list<array<string, mixed>>  $problems  draft problems
     * @param  array<string, mixed>  $diff
     * @return array<string, mixed>
     */
    public function analyze(Form $form, array $definition, ?array $published, array $operations, array $steps, array $problems, array $diff): array
    {
        $blocking = [];
        foreach ($problems as $p) {
            $blocking[] = ['code' => 'draft_problem', 'path' => $p['path'], 'message' => $p['message'], 'detail' => $p['code']];
        }
        $table = $form->table_name;
        $tableExists = in_array(strtolower($table), array_map('strtolower', $this->driver->tables()), true);
        $recordCount = $tableExists ? (int) DB::table($table)->count() : 0;

        // Type changes: rows whose values do not convert.
        $typeConflicts = [];
        foreach ($operations as $op) {
            if ($op['op'] === 'validate_data' && in_array(strtolower($op['table']), array_map('strtolower', $this->driver->tables()), true)) {
                $conflicts = $this->executor->conflicts($op['table'], $op['column'], $op['to'], 20);
                if ($conflicts !== []) {
                    $typeConflicts[] = ['table' => $op['table'], 'column' => $op['column'], 'to' => $op['to']['type'], 'records' => $conflicts];
                    $blocking[] = ['code' => 'type_conflict', 'path' => $op['column'], 'message' => count($conflicts).' existing value(s) of '.$op['column'].' cannot be converted. Edit those records, choose another type, or cancel.'];
                }
            }
        }
        // New NOT NULL columns without a default on tables that already hold rows.
        foreach ($operations as $op) {
            if ($op['op'] === 'add_column' && ! $op['column']['nullable'] && ($op['column']['default'] ?? null) === null && ! str_ends_with($op['column']['name'], '__new')) {
                $exists = in_array(strtolower($op['table']), array_map('strtolower', $this->driver->tables()), true);
                if ($exists && DB::table($op['table'])->exists()) {
                    $blocking[] = ['code' => 'not_null_without_default', 'path' => $op['column']['name'], 'message' => 'The column '.$op['column']['name'].' cannot be added as "not empty" to a table that already has records unless it has a database default.'];
                }
            }
        }
        // Existing records failing newly required fields.
        $failingRequired = [];
        if ($recordCount > 0 && $published !== null) {
            $before = array_column($published['fields'] ?? [], null, 'uuid');
            foreach ($definition['fields'] as $f) {
                $wasRequired = (bool) ($before[$f['uuid']]['validation']['required'] ?? false);
                $isRequired = (bool) ($f['validation']['required'] ?? false);
                $col = $this->mainColumn($definition, $f['uuid']);
                if ($isRequired && ! $wasRequired && $col !== null && isset($before[$f['uuid']])) {
                    $n = (int) DB::table($table)->whereNull($col)->whereNull('deleted_at')->count();
                    if ($n > 0) {
                        $failingRequired[] = ['field' => $f['uuid'], 'key' => $f['key'], 'records' => $n];
                    }
                }
            }
        }

        // Access rules that target removed fields or groups.
        $removedFields = array_column(array_filter($diff['fields'] ?? [], static fn ($e) => $e['change'] === 'removed'), 'uuid');
        $removedGroups = array_column(array_filter($diff['groups'] ?? [], static fn ($e) => $e['change'] === 'removed'), 'uuid');
        $orphanRules = DB::table('field_access_rules')->where('form_id', $form->id)
            ->where(function ($q) use ($removedFields, $removedGroups): void {
                $q->whereIn('field_id', DB::table('fields')->whereIn('uuid', $removedFields ?: ['-'])->select('id'))
                    ->orWhereIn('group_id', DB::table('field_groups')->whereIn('uuid', $removedGroups ?: ['-'])->select('id'));
            })->count();

        // Linked forms: relations from other forms to this one, and what they display.
        $linked = [];
        foreach (Relation::query()->where('target_form_id', $form->id)->where('source_form_id', '!=', $form->id)->with('source:id,uuid,key')->get() as $r) {
            $display = DB::table('fields')->where('id', $r->display_field_id)->value('uuid');
            $linked[] = [
                'form' => $r->source?->uuid, 'form_key' => $r->source?->key, 'relation' => $r->key,
                'broken' => $display !== null && in_array($display, $removedFields, true),
            ];
            if ($display !== null && in_array($display, $removedFields, true)) {
                $blocking[] = ['code' => 'linked_form_reference', 'path' => $r->key, 'message' => 'The form '.$r->source?->key.' shows a field you are removing; choose another display field there first.'];
            }
        }
        // Other forms' published conditions/formulas that read this form's removed fields through relation paths.
        $removedKeys = array_column(array_filter($diff['fields'] ?? [], static fn ($e) => $e['change'] === 'removed'), 'key');

        $blockingSteps = array_values(array_filter($steps, fn (array $s): bool => ! $s['is_online'] && ($s['rows'] ?? 0) >= (int) $this->settings->get('schema', 'blocking_confirmation_rows')));
        $changeClass = MigrationPlanner::changeClass($operations);
        $destructive = array_values(array_filter($steps, static fn (array $s) => $s['is_destructive']));

        return [
            'records' => [
                'count' => $recordCount,
                'failing_required' => $failingRequired,
                'type_conflicts' => $typeConflicts,
            ],
            'diff' => $diff['summary'] ?? [],
            'removed_fields' => $removedKeys,
            'permissions' => ['orphaned_access_rules' => $orphanRules],
            'linked_forms' => $linked,
            'menus' => MenuItem::query()->where('target_type', 'form')->where('target_id', $form->id)->count(),
            // Views, actions, download profiles, notifications and reports arrive in later phases;
            // nothing can reference this form through them yet.
            'dependents' => ['views' => 0, 'actions' => 0, 'download_profiles' => 0, 'notifications' => 0, 'reports' => 0],
            'schema' => [
                'steps' => count($steps),
                'online' => count(array_filter($steps, static fn ($s) => $s['is_online'])),
                'blocking' => array_map(static fn ($s) => ['sequence' => $s['sequence'], 'operation' => $s['operation'], 'table' => $s['table_name'], 'rows' => $s['rows'], 'estimated_ms' => $s['estimated_ms']], $blockingSteps),
                'estimated_ms' => array_sum(array_column($steps, 'estimated_ms')),
                'backup' => $destructive === [] ? null : ['tables' => array_values(array_unique(array_column($destructive, 'table_name'))), 'rows' => $recordCount, 'retention_days' => (int) $this->settings->get('schema', 'snapshot_retention_days')],
                'change_class' => $changeClass,
                'plan' => array_map(static fn ($s) => array_intersect_key($s, array_flip(['sequence', 'operation', 'table_name', 'is_destructive', 'is_online', 'estimated_ms', 'lock_level', 'rows', 'sql_preview'])), $steps),
            ],
            'requires_confirmation' => [
                'blocking_steps' => $blockingSteps !== [],
                'destructive' => $changeClass === 'destructive',
                'typed' => $changeClass === 'destructive' ? $form->key : null,
                'failing_required' => $failingRequired !== [],
            ],
            'blocking' => $blocking,
        ];
    }

    /** @param  array<string, mixed>  $definition */
    private function mainColumn(array $definition, string $fieldUuid): ?string
    {
        foreach ($definition['schema']['tables'][0]['columns'] ?? [] as $c) {
            if (($c['field'] ?? null) === $fieldUuid && ! isset($c['part'])) {
                return $c['name'];
            }
        }

        return null;
    }
}
