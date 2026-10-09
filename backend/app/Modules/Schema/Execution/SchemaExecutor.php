<?php

declare(strict_types=1);

namespace App\Modules\Schema\Execution;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Schema\Models\MigrationPlan;
use App\Modules\Schema\Models\MigrationStep;
use App\Modules\Schema\Planning\StepSql;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs a persisted migration plan step by step (architecture §12.2). DDL is
 * never assumed transactional: each step is recorded as applied before the
 * next starts. On failure the applied steps are reversed in reverse order;
 * if any reverse fails the plan is `inconsistent` and the caller must lock the
 * form (schema_inconsistent).
 */
final class SchemaExecutor
{
    /** Rows per batch for data steps. */
    public const CHUNK = 500;

    public function __construct(private readonly DatabaseDriver $driver, private readonly AuditWriter $audit) {}

    /** @return string final plan status: applied | reversed | inconsistent */
    public function run(MigrationPlan $plan): string
    {
        $plan->forceFill(['status' => 'running', 'started_at' => $plan->started_at ?? Carbon::now('UTC'), 'error' => null])->save();
        $steps = $plan->steps()->get();
        foreach ($steps as $step) {
            if (in_array($step->status, ['applied', 'skipped'], true)) {
                continue;
            }
            $retrying = $step->started_at !== null; // attempted before: a retry
            try {
                $this->apply($step, $step->forward, $retrying);
                $plan->increment('steps_applied');
            } catch (Throwable $e) {
                $message = $this->message($e);
                $step->forceFill(['status' => 'failed', 'error' => $message])->save();
                $plan->forceFill(['status' => 'reversing', 'error' => $message])->save();
                $this->audit->record('schema.step_failed', 'schema', null, 'migration_plan', $plan->id, ['step' => $step->sequence, 'operation' => $step->operation, 'error' => $message]);

                return $this->reverse($plan);
            }
        }
        $plan->forceFill(['status' => 'applied', 'finished_at' => Carbon::now('UTC')])->save();

        return 'applied';
    }

    /**
     * Reverses every applied step, newest first.
     *
     * @return string reversed | inconsistent
     */
    public function reverse(MigrationPlan $plan): string
    {
        $plan->forceFill(['status' => 'reversing'])->save();
        $ok = true;
        foreach ($plan->steps()->get()->sortByDesc('sequence') as $step) {
            if (! in_array($step->status, ['applied', 'reverse_failed'], true)) {
                continue;
            }
            try {
                $started = microtime(true);
                $this->execute($step->reverse, true);
                $step->forceFill(['status' => 'reversed', 'reversed_at' => Carbon::now('UTC'), 'error' => null, 'duration_ms' => (int) ((microtime(true) - $started) * 1000)])->save();
                $plan->decrement('steps_applied');
            } catch (Throwable $e) {
                $ok = false;
                $step->forceFill(['status' => 'reverse_failed', 'error' => $this->message($e)])->save();
            }
        }
        $status = $ok ? 'reversed' : 'inconsistent';
        $plan->forceFill(['status' => $status, 'finished_at' => Carbon::now('UTC')])->save();

        return $status;
    }

    /** Re-runs the remaining (pending/failed) steps of an inconsistent or reversed plan from the repair screen. */
    public function retry(MigrationPlan $plan): string
    {
        foreach ($plan->steps()->get() as $step) {
            if (in_array($step->status, ['failed', 'reversed', 'reverse_failed'], true)) {
                $step->forceFill(['status' => $step->status === 'reverse_failed' ? 'applied' : 'pending', 'error' => null])->save();
            }
        }
        if ($plan->steps()->where('status', 'applied')->exists() && $plan->steps()->where('status', 'reverse_failed')->exists()) {
            return $this->reverse($plan);
        }

        return $this->run($plan->refresh());
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function apply(MigrationStep $step, array $spec, bool $retrying): void
    {
        $step->forceFill(['status' => 'pending', 'started_at' => Carbon::now('UTC')])->save();
        $started = microtime(true);
        $this->execute($spec, $retrying);
        $step->forceFill(['status' => 'applied', 'applied_at' => Carbon::now('UTC'), 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'error' => null])->save();
    }

    /** @param  array<string, mixed>  $spec */
    public function execute(array $spec, bool $retrying = false): void
    {
        match ($spec['op']) {
            'validate_data' => $this->validateData($spec),
            'copy_data' => $this->copyData($spec),
            'noop' => null,
            default => $this->ddl($spec, $retrying),
        };
    }

    /** @param  array<string, mixed>  $spec */
    private function ddl(array $spec, bool $retrying): void
    {
        // Retries and reverses are idempotent: skip a step whose effect is already
        // visible (a first run never skips, so unexpected drift fails loudly).
        if ($retrying && $this->alreadyApplied($spec)) {
            return;
        }
        foreach (StepSql::for($this->driver, $spec) as $sql) {
            DB::statement($sql);
        }
    }

    /** @param  array<string, mixed>  $spec */
    private function alreadyApplied(array $spec): bool
    {
        $tables = array_map('strtolower', $this->driver->tables());
        $hasTable = static fn (string $t): bool => in_array(strtolower($t), $tables, true);
        $hasColumn = fn (string $t, string $c): bool => $hasTable($t) && in_array(strtolower($c), array_map(static fn (array $col) => strtolower($col['name']), $this->driver->columns($t)), true);

        return match ($spec['op']) {
            'create_table', 'create_pivot' => $hasTable($spec['table']),
            'rename_table', 'drop_table_archive', 'restore_table' => ! $hasTable($spec['from'] ?? $spec['table']) && $hasTable($spec['to'] ?? $spec['archived']),
            'add_column' => $hasColumn($spec['table'], $spec['column']['name']),
            'rename_column' => ! $hasColumn($spec['table'], $spec['from']) && $hasColumn($spec['table'], $spec['to']),
            'archive_column' => ! $hasColumn($spec['table'], $spec['column']) && $hasColumn($spec['table'], $spec['archived']),
            'restore_column' => $hasColumn($spec['table'], $spec['column']) && ! $hasColumn($spec['table'], $spec['archived']),
            default => false,
        };
    }

    /** @param  array<string, mixed>  $spec */
    private function validateData(array $spec): void
    {
        $conflicts = $this->conflicts($spec['table'], $spec['column'], $spec['to'], 100);
        if ($conflicts !== []) {
            throw new StepFailed(count($conflicts).' existing value(s) cannot be converted to the new type.', $conflicts);
        }
    }

    /**
     * Rows whose value of `$column` cannot be converted to `$to`.
     *
     * @param  array<string, mixed>  $to
     * @return list<array{id: int|string, value: mixed}>
     */
    public function conflicts(string $table, string $column, array $to, int $limit = 100): array
    {
        $conflicts = [];
        DB::table($table)->whereNotNull($column)->select(['id', $column])->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$conflicts, $column, $to, $limit): bool {
                foreach ($rows as $row) {
                    [$ok] = ValueConverter::convert($row->{$column}, $to);
                    if (! $ok) {
                        $conflicts[] = ['id' => $row->id, 'value' => $row->{$column}];
                        if (count($conflicts) >= $limit) {
                            return false;
                        }
                    }
                }

                return true;
            });

        return $conflicts;
    }

    /** @param  array<string, mixed>  $spec */
    private function copyData(array $spec): void
    {
        DB::table($spec['table'])->whereNotNull($spec['from'])->select(['id', $spec['from']])->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use ($spec): void {
                DB::transaction(function () use ($rows, $spec): void {
                    foreach ($rows as $row) {
                        [$ok, $value] = ValueConverter::convert($row->{$spec['from']}, $spec['toSpec']);
                        if (! $ok) {
                            throw new StepFailed("Record {$row->id} cannot be converted.", [['id' => $row->id, 'value' => $row->{$spec['from']}]]);
                        }
                        DB::table($spec['table'])->where('id', $row->id)->update([$spec['to'] => $value]);
                    }
                });
            });
    }

    private function message(Throwable $e): string
    {
        return mb_substr($e->getMessage(), 0, 2000);
    }
}
