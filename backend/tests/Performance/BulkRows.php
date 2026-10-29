<?php

declare(strict_types=1);

namespace Tests\Performance;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates millions of rows inside the database with INSERT … SELECT over a
 * numbers table, in batches of 100 000, on MySQL and SQL Server alike. Rows
 * get explicit ids (next after the table's current maximum), so child rows
 * and lookups can point at them by arithmetic.
 *
 * Column values come from the published form's compiled schema: every
 * column of the record table gets a value of its logical type, so a schema
 * change in the form never needs a change here.
 */
final class BulkRows
{
    public const BATCH = 100_000;

    private string $driver;

    public function __construct(private readonly Closure $log)
    {
        $this->driver = DB::connection()->getDriverName();
        if (! in_array($this->driver, ['mysql', 'sqlsrv'], true)) {
            throw new RuntimeException("Unsupported database driver {$this->driver}.");
        }
    }

    /** The numbers table (1 … 1000) every generator selects from. */
    public function prepare(): void
    {
        if (DB::getSchemaBuilder()->hasTable('perf_numbers')) {
            return;
        }
        DB::getSchemaBuilder()->create('perf_numbers', static function ($t): void {
            $t->integer('n')->primary();
        });
        foreach (array_chunk(range(1, 1000), 500) as $chunk) {
            DB::table('perf_numbers')->insert(array_map(static fn (int $n) => ['n' => $n], $chunk));
        }
    }

    /** Next free id of a table. */
    public function base(string $table): int
    {
        return (int) DB::table($table)->max('id');
    }

    /**
     * Inserts $count rows into $table; $columns maps each column to an SQL
     * expression over `s.n` (1 … count). Returns the id base: row n has id base + n.
     *
     * @param  array<string, string>  $columns
     */
    public function insert(string $table, int $count, array $columns, bool $explicitIds = true): int
    {
        $base = $this->base($table);
        if ($explicitIds) {
            $columns = ['id' => "{$base} + s.n"] + $columns;
        }
        $names = implode(', ', array_map(fn (string $c) => $this->wrap($c), array_keys($columns)));
        $exprs = implode(', ', array_values($columns));
        $done = 0;
        $started = microtime(true);
        while ($done < $count) {
            $size = min(self::BATCH, $count - $done);
            $blocks = (int) ceil($size / 1000);
            $sql = "insert into {$this->wrap($table)} ({$names}) select {$exprs} from ("
                ."select {$done} + (a.n - 1) * 1000 + b.n as n from perf_numbers a cross join perf_numbers b where a.n <= {$blocks}"
                .') s where s.n <= '.($done + $size);
            $this->run($table, $sql, $explicitIds);
            $done += $size;
        }
        ($this->log)(sprintf('  %s: %s rows in %.1f s', $table, number_format($count), microtime(true) - $started));

        return $base;
    }

    // SQL helpers, one spelling per engine.

    public function idiv(string $a, int $b): string
    {
        return $this->driver === 'mysql' ? "(({$a}) div {$b})" : "(({$a}) / {$b})";
    }

    /** A timestamp $secondsExpr seconds before the dataset's reference time. */
    public function ago(string $secondsExpr, string $reference): string
    {
        return $this->driver === 'mysql'
            ? "timestamp('{$reference}') - interval ({$secondsExpr}) second"
            : "dateadd(second, -({$secondsExpr}), cast('{$reference}' as datetime2(6)))";
    }

    public function dateOf(string $expr): string
    {
        return "cast({$expr} as date)";
    }

    public function timeOf(string $secondsExpr): string
    {
        return $this->driver === 'mysql' ? "sec_to_time({$secondsExpr})" : "cast(dateadd(second, {$secondsExpr}, 0) as time)";
    }

    public function uuid(): string
    {
        return $this->driver === 'mysql' ? 'uuid()' : 'lower(convert(char(36), newid()))';
    }

    /** @param  list<string>  $parts */
    public function concat(array $parts): string
    {
        // CONCAT converts numbers implicitly on both engines (SQL Server since 2012).
        return 'concat('.implode(', ', $parts).')';
    }

    /** @param  list<string>  $values */
    public function pick(string $indexExpr, array $values): string
    {
        $cases = '';
        foreach ($values as $i => $v) {
            $cases .= " when {$i} then ".DB::getPdo()->quote($v);
        }

        return '(case ('.$indexExpr.') % '.count($values).$cases.' end)';
    }

    public function wrap(string $identifier): string
    {
        return $this->driver === 'mysql' ? '`'.$identifier.'`' : '['.$identifier.']';
    }

    private function run(string $table, string $sql, bool $explicitIds): void
    {
        if ($this->driver === 'sqlsrv' && $explicitIds) {
            // Explicit ids need IDENTITY_INSERT for the statement; the identity moves past the largest one.
            DB::unprepared("set identity_insert {$this->wrap($table)} on; {$sql}; set identity_insert {$this->wrap($table)} off;");

            return;
        }
        DB::statement($sql);
    }
}
