<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monthly time partitions of the logs that grow without bound (architecture
 * §19.16): audit_logs and error_logs are partitioned on occurred_at, and the
 * twelve months ahead always exist, so an insert never lands in a catch-all
 * partition that would have to be split later.
 */
final class Partitions
{
    /** The tables partitioned by month on occurred_at. */
    public const TABLES = ['audit_logs', 'error_logs'];

    /** How many month bounds ahead of the current one always exist. */
    public const MONTHS_AHEAD = 12;

    public function __construct(private readonly DatabaseDriver $driver) {}

    /**
     * Partitions a table by month when it is not already; rows before the
     * first bound (everything written so far) stay in the first partition.
     */
    public function partition(string $table): void
    {
        if ($this->driver->timePartitionBounds($table) !== []) {
            return;
        }
        foreach ($this->driver->createTimePartitions($table, 'occurred_at', $this->boundsFrom($this->firstBound())) as $sql) {
            DB::statement($sql);
        }
    }

    /**
     * Adds the months missing ahead of today on every partitioned table.
     *
     * @return array<string, int> partitions added per table
     */
    public function maintain(): array
    {
        $added = [];
        foreach (self::TABLES as $table) {
            $have = $this->driver->timePartitionBounds($table);
            if ($have === []) {
                $this->partition($table);
                $added[$table] = self::MONTHS_AHEAD + 1;

                continue;
            }
            $last = CarbonImmutable::parse(end($have));
            $want = $this->firstBound()->addMonths(self::MONTHS_AHEAD);
            $bounds = [];
            for ($b = $last->addMonth(); $b <= $want; $b = $b->addMonth()) {
                $bounds[] = $b->toDateString();
            }
            foreach ($this->driver->addTimePartitions($table, $bounds) as $sql) {
                DB::statement($sql);
            }
            $added[$table] = count($bounds);
        }

        return $added;
    }

    /**
     * The partitions of a table as month ranges, oldest first, each with its
     * row count: what the retention screen shows and archives by.
     *
     * @return list<array{from: string|null, to: string, rows: int}>
     */
    public function describe(string $table): array
    {
        $out = [];
        $from = null;
        foreach ($this->driver->timePartitionBounds($table) as $to) {
            $q = DB::table($table)->where('occurred_at', '<', $to);
            if ($from !== null) {
                $q->where('occurred_at', '>=', $from);
            }
            $out[] = ['from' => $from, 'to' => $to, 'rows' => $q->count()];
            $from = $to;
        }

        return $out;
    }

    /** The first bound: the start of next month (UTC). */
    private function firstBound(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfMonth()->addMonth();
    }

    /** @return list<string> */
    private function boundsFrom(CarbonImmutable $first): array
    {
        $bounds = [];
        for ($i = 0; $i <= self::MONTHS_AHEAD; $i++) {
            $bounds[] = $first->addMonths($i)->toDateString();
        }

        return $bounds;
    }
}
