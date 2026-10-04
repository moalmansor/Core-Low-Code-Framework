<?php

declare(strict_types=1);

namespace App\Modules\Schema\Execution;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Forms\Definition\Introspection;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Models\Form;
use App\Modules\Schema\Models\SchemaReconciliationReport;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Compares, per form, the schema its current published version expects with
 * the live engine (architecture §12.6): missing or extra tables and columns,
 * type category, nullability, indexes and foreign keys. Archived columns
 * (`zz_…`) are expected leftovers and never reported.
 */
final class SchemaReconciler
{
    /** Live type categories accepted for each expected logical type. */
    private const COMPATIBLE = [
        'id' => ['bigint'], 'bigint' => ['bigint'], 'int' => ['int'], 'smallint' => ['smallint', 'int'],
        'bool' => ['bool'], 'decimal' => ['decimal'], 'string' => ['string'], 'code' => ['string'], 'hash' => ['string'],
        'uuid' => ['uuid', 'string'], 'text' => ['text', 'string'], 'longtext' => ['text'], 'json' => ['json', 'text'],
        'date' => ['date'], 'datetime' => ['datetime'], 'time' => ['time'],
    ];

    public function __construct(private readonly DatabaseDriver $driver, private readonly PublishedDefinitions $definitions) {}

    public function run(?Form $only, string $trigger, ?int $userId): SchemaReconciliationReport
    {
        $report = SchemaReconciliationReport::query()->create([
            'form_id' => $only?->id, 'trigger' => $trigger, 'engine' => $this->driver->name(), 'status' => 'running',
            'difference_count' => 0, 'differences' => [], 'triggered_by' => $userId, 'started_at' => Carbon::now('UTC'),
        ]);
        try {
            $differences = $this->differences($only);
            $report->forceFill([
                'status' => $differences === [] ? 'clean' : 'drift',
                'difference_count' => count($differences),
                'differences' => $differences,
                'finished_at' => Carbon::now('UTC'),
            ])->save();
        } catch (Throwable $e) {
            $report->forceFill(['status' => 'error', 'differences' => [['kind' => 'error', 'message' => mb_substr($e->getMessage(), 0, 1000)]], 'finished_at' => Carbon::now('UTC')])->save();
        }

        return $report;
    }

    /** @return list<array<string, mixed>> */
    public function differences(?Form $only): array
    {
        $forms = Form::query()->whereNotNull('current_version_id')->when($only !== null, fn ($q) => $q->whereKey($only->id))->get();
        $expected = $this->expectedTables($forms->all());
        $liveTables = array_map('strtolower', $this->driver->tables());
        $out = [];
        foreach ($expected as $tableName => $exp) {
            $form = $exp['form'];
            if (! in_array(strtolower($tableName), $liveTables, true)) {
                $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'missing_table', 'expected' => $tableName, 'actual' => null];

                continue;
            }
            $live = [];
            foreach ($this->driver->columns($tableName) as $c) {
                $live[strtolower($c['name'])] = $c;
            }
            foreach ($exp['columns'] as $c) {
                $l = $live[strtolower($c['name'])] ?? null;
                if ($l === null) {
                    $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'missing_column', 'column' => $c['name'], 'expected' => $c['type'], 'actual' => null];

                    continue;
                }
                if ($c['bound'] ?? false) {
                    continue;
                }
                $category = Introspection::logicalType($l['type']);
                if (! in_array($category, self::COMPATIBLE[$c['type']] ?? [$c['type']], true)) {
                    $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'type_mismatch', 'column' => $c['name'], 'expected' => $c['type'], 'actual' => $l['type']];
                }
                if ((bool) $c['nullable'] !== (bool) $l['nullable'] && $c['type'] !== 'id') {
                    $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'nullability', 'column' => $c['name'], 'expected' => $c['nullable'] ? 'null' : 'not null', 'actual' => $l['nullable'] ? 'null' : 'not null'];
                }
            }
            if (! $exp['bound']) {
                $known = array_map(static fn (array $c) => strtolower($c['name']), $exp['columns']);
                foreach ($live as $name => $l) {
                    if (! in_array($name, $known, true) && ! str_starts_with($name, 'zz_')) {
                        $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'extra_column', 'column' => $l['name'], 'expected' => null, 'actual' => $l['type']];
                    }
                }
            }
            $liveIndexes = array_map(static fn (array $i) => strtolower($i['name']), array_filter($this->driver->indexes($tableName), static fn (array $i) => ! $i['primary']));
            foreach ($exp['indexes'] as $i) {
                if (! in_array(strtolower($i['name']), $liveIndexes, true)) {
                    $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'index', 'name' => $i['name'], 'expected' => $i['columns'], 'actual' => null];
                }
            }
            $liveFks = array_map(static fn (array $f) => strtolower((string) $f['name']), $this->driver->foreignKeys($tableName));
            foreach ($exp['foreignKeys'] as $f) {
                if (! in_array(strtolower($f['name']), $liveFks, true)) {
                    $out[] = ['form' => $form, 'table' => $tableName, 'kind' => 'fk', 'name' => $f['name'], 'expected' => $f['column'].' → '.$f['references'], 'actual' => null];
                }
            }
        }
        $keys = $forms->pluck('key', 'uuid')->all();
        foreach ($out as $i => $difference) {
            $out[$i]['form_key'] = $keys[$difference['form']] ?? null;
        }

        return $out;
    }

    /**
     * Expected tables of the given forms, with the FK columns other forms'
     * inline sub-forms add to them.
     *
     * @param  list<Form>  $forms
     * @return array<string, array{form: string, bound: bool, columns: list<array<string, mixed>>, indexes: list<array<string, mixed>>, foreignKeys: list<array<string, mixed>>}>
     */
    private function expectedTables(array $forms): array
    {
        $out = [];
        $all = Form::query()->whereNotNull('current_version_id')->get();
        $wanted = array_flip(array_map(static fn (Form $f) => $f->id, $forms));
        foreach ($all as $form) {
            $def = $this->definitions->version($form->id, (int) $form->current_version_id);
            foreach ($def['schema']['tables'] ?? [] as $t) {
                if (isset($wanted[$form->id])) {
                    $out[$t['name']] = ['form' => $form->uuid, 'bound' => $form->binding_mode === 'bound' && $t['role'] === 'main', 'columns' => $t['columns'], 'indexes' => $t['indexes'], 'foreignKeys' => $t['foreignKeys']];
                }
            }
        }
        foreach ($all as $form) {
            $def = $this->definitions->version($form->id, (int) $form->current_version_id);
            foreach ($def['schema']['external'] ?? [] as $e) {
                if (isset($out[$e['table']])) {
                    $out[$e['table']]['columns'][] = $e['column'];
                    $out[$e['table']]['indexes'][] = $e['index'];
                    $out[$e['table']]['foreignKeys'][] = $e['foreignKey'];
                }
            }
        }

        return $out;
    }
}
