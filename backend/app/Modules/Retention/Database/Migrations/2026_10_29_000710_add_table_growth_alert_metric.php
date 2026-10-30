<?php

declare(strict_types=1);

use App\Infrastructure\Database\Naming;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Table growth warnings (specification §4.26): the Operations Center's alert
 * rules can watch the size of the largest table, alongside the storage volume.
 */
return new class extends Migration
{
    private const BEFORE = ['failed_submissions', 'failed_jobs', 'failed_emails', 'stuck_emails', 'failed_webhooks', 'failed_integrations', 'queue_size', 'error_rate', 'storage_usage', 'scheduler_lag'];

    public function up(): void
    {
        $this->replace([...array_slice(self::BEFORE, 0, 9), 'largest_table_mb', 'scheduler_lag']);
    }

    public function down(): void
    {
        DB::table('operations_alert_rules')->where('metric', 'largest_table_mb')->delete();
        $this->replace(self::BEFORE);
    }

    /** @param list<string> $values */
    private function replace(array $values): void
    {
        $name = Naming::constraint('ck', 'operations_alert_rules', ['metric']);
        $grammar = DB::connection()->getQueryGrammar();
        $list = implode(', ', array_map(static fn (string $v): string => "'{$v}'", $values));
        DB::statement(DB::getDriverName() === 'sqlsrv'
            ? "alter table {$grammar->wrapTable('operations_alert_rules')} drop constraint {$grammar->wrap($name)}"
            : "alter table {$grammar->wrapTable('operations_alert_rules')} drop check {$grammar->wrap($name)}");
        DB::statement("alter table {$grammar->wrapTable('operations_alert_rules')} add constraint {$grammar->wrap($name)} check ({$grammar->wrap('metric')} in ({$list}))");
    }
};
