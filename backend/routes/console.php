<?php

declare(strict_types=1);

use App\Modules\Assignment\Claims;
use App\Modules\Assignment\Http\Controllers\DelegationController;
use App\Modules\Audit\AuditWriter;
use App\Modules\Audit\ChainVerifier;
use App\Modules\Core\Outbox\OutboxRelay;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Monitoring\ErrorReporter;
use App\Modules\Records\Exchange\ExportService;
use App\Modules\Records\FileStore;
use App\Modules\Schema\Execution\SchemaReconciler;
use App\Modules\Schema\Execution\Snapshots;
use App\Modules\Setup\SetupState;
use App\Modules\Workflow\Runtime\SlaTimers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('records:expire-exports', function (ExportService $exports): int {
    $this->info('Expired '.$exports->expire().' export(s).');

    return 0;
})->purpose('Delete export files past their expiry time');

Artisan::command('outbox:relay {--limit=200}', function (OutboxRelay $relay): int {
    $count = $relay->relayPending((int) $this->option('limit'));
    $this->info("Relayed {$count} event(s).");

    return 0;
})->purpose('Deliver committed outbox events to their handlers');

Artisan::command('audit:verify {--full : Re-verify every chain from its first entry}', function (ChainVerifier $verifier, AuditWriter $audit, ErrorReporter $errors): int {
    $result = $verifier->verify((bool) $this->option('full'));
    $this->info("Verified {$result['verified']} entr(ies).");
    if ($result['breaks'] === []) {
        return 0;
    }
    foreach ($result['breaks'] as $break) {
        $this->error(sprintf('Chain %d broken at sequence %d (entry %d): %s', $break['chain_id'], $break['chain_seq'], $break['id'], $break['reason']));
    }
    $audit->record('audit.chain_break_detected', 'security', meta: ['breaks' => $result['breaks']]);
    $errors->report(new RuntimeException('Audit hash chain verification found '.count($result['breaks']).' break(s).'), 'critical');

    return 1;
})->purpose('Verify the audit log hash chains and report breaks');

Artisan::command('db:ensure {--timeout=180 : Seconds to wait for the database server}', function (): int {
    // Creates the configured database, with the collation architecture §9
    // prescribes, when it does not exist yet; never alters or drops one.
    $name = (string) config('database.default');
    $config = (array) config("database.connections.{$name}");
    $driver = (string) ($config['driver'] ?? '');
    $database = (string) ($config['database'] ?? '');
    if (! in_array($driver, ['mysql', 'sqlsrv'], true)) {
        $this->error("Unsupported database driver [{$driver}].");

        return 1;
    }
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $database) !== 1) {
        $this->error('DB_DATABASE must start with a letter and contain only letters, digits, and underscores.');

        return 1;
    }

    // Connect to the server rather than to the (possibly missing) database.
    config(['database.connections.lcf_server' => array_merge($config, ['database' => $driver === 'sqlsrv' ? 'master' : null])]);
    $deadline = time() + max(0, (int) $this->option('timeout'));
    $waiting = false;
    while (true) {
        try {
            $server = DB::connection('lcf_server');
            $server->getPdo();
            break;
        } catch (Throwable $e) {
            DB::purge('lcf_server');
            if (time() >= $deadline) {
                $this->error('The database server did not accept a connection: '.$e->getMessage());

                return 1;
            }
            if (! $waiting) {
                $this->line('Waiting for the database server…');
                $waiting = true;
            }
            sleep(2);
        }
    }

    $exists = $driver === 'sqlsrv'
        ? $server->selectOne('SELECT DB_ID(?) AS id', [$database])?->id !== null
        : $server->selectOne('SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]) !== null;
    if ($exists) {
        $this->line("Database [{$database}] exists.");
        DB::purge('lcf_server');

        return 0;
    }

    if ($driver === 'sqlsrv') {
        $server->statement("CREATE DATABASE [{$database}] COLLATE Arabic_100_CI_AI_SC");
        $server->statement("ALTER DATABASE [{$database}] SET READ_COMMITTED_SNAPSHOT ON");
    } else {
        $server->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    }
    DB::purge('lcf_server');
    $this->info("Created database [{$database}].");

    return 0;
})->purpose('Create the configured database with the prescribed collation when it does not exist');

Artisan::command('security:prune', function (): int {
    $cutoff = now()->subDays(90)->format('Y-m-d H:i:s.u');
    $deleted = DB::table('login_attempts')->where('attempted_at', '<', $cutoff)->delete();
    $this->info("Pruned {$deleted} login attempt(s).");

    return 0;
})->purpose('Remove expired security bookkeeping rows');

Artisan::command('files:purge-temporary {--hours=24}', function (FileStore $files): int {
    $this->info('Purged '.$files->purgeTemporary(max(1, (int) $this->option('hours'))).' temporary upload(s).');

    return 0;
})->purpose('Delete uploads that no record adopted');

Artisan::command('schema:purge-snapshots', function (Snapshots $snapshots): int {
    $this->info('Purged '.$snapshots->purgeExpired().' expired schema snapshot(s).');

    return 0;
})->purpose('Delete pre-publish snapshots past their retention');

Artisan::command('schema:reconcile {--scheduled : Run only when daily reconciliation is enabled in the settings}', function (SchemaReconciler $reconciler, SettingsService $settings, ErrorReporter $errors): int {
    if ($this->option('scheduled') && ! $settings->get('schema', 'reconcile_daily')) {
        $this->info('Daily reconciliation is disabled.');

        return 0;
    }
    $report = $reconciler->run(null, $this->option('scheduled') ? 'scheduled' : 'on_demand', null);
    $this->info("Reconciliation {$report->status}: {$report->difference_count} difference(s).");
    if ($report->status !== 'clean') {
        $errors->report(new RuntimeException("Schema reconciliation found {$report->difference_count} difference(s) (report {$report->id})."), 'warning');

        return 1;
    }

    return 0;
})->purpose('Compare form metadata with the physical schema and report every difference');

Artisan::command('sla:tick', function (SlaTimers $timers): int {
    $this->info('Processed '.$timers->tick().' SLA timer(s).');

    return 0;
})->purpose('Warn, mark breaches and run SLA escalations that are due');

Artisan::command('work:maintain', function (Claims $claims, OutboxWriter $outbox): int {
    $expired = $claims->expire();
    $delegations = DelegationController::refreshStatuses();
    // Approvers who have not decided by the request's due date are reminded once.
    $now = now('UTC')->format('Y-m-d H:i:s.u');
    $due = DB::table('approval_decisions')->join('approval_requests', 'approval_requests.id', '=', 'approval_decisions.approval_request_id')
        ->where('approval_requests.status', 'pending')->whereNotNull('approval_requests.due_at')->where('approval_requests.due_at', '<', $now)
        ->where('approval_decisions.decision', 'pending')->whereNull('approval_decisions.reminded_at')
        ->limit(500)->get(['approval_decisions.id', 'approval_decisions.approver_type', 'approval_decisions.approver_id', 'approval_requests.uuid']);
    foreach ($due as $d) {
        DB::table('approval_decisions')->where('id', $d->id)->update(['reminded_at' => $now, 'updated_at' => $now]);
        $outbox->publish('approval.reminder', ['approval' => strtolower((string) $d->uuid), 'approver' => ['type' => $d->approver_type, 'id' => (int) $d->approver_id]]);
    }
    $this->info("Released {$expired} claim(s), updated {$delegations} delegation(s), reminded ".count($due).' approver(s).');

    return 0;
})->purpose('Release timed-out claims, refresh delegation states and remind overdue approvers');

Schedule::command('sla:tick')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('work:maintain')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('files:purge-temporary')->hourly()->onOneServer();
Schedule::command('records:expire-exports')->hourly()->onOneServer();
Schedule::command('schema:purge-snapshots')->dailyAt('04:10')->onOneServer();
Schedule::command('schema:reconcile --scheduled')->dailyAt('01:40')->withoutOverlapping()->onOneServer();
Schedule::command('outbox:relay')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('audit:verify')->dailyAt('02:10')->withoutOverlapping()->onOneServer();
Schedule::command('audit:verify --full')->weeklyOn(0, '03:10')->withoutOverlapping()->onOneServer();
Schedule::command('security:prune')->dailyAt('03:40')->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();

Artisan::command('setup:token', function (SetupState $state): int {
    if ($state->isComplete()) {
        $this->error('Setup is already complete; the wizard is locked.');

        return 1;
    }
    $token = $state->issueToken();
    $this->line('Setup token (enter it in the setup wizard; any earlier token is now invalid):');
    $this->info($token);

    return 0;
})->purpose('Issue the one-time token that unlocks the first-run setup wizard');
