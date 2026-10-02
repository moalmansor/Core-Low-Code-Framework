<?php

declare(strict_types=1);

use App\Modules\Audit\AuditWriter;
use App\Modules\Audit\ChainVerifier;
use App\Modules\Core\Outbox\OutboxRelay;
use App\Modules\Monitoring\ErrorReporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

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
    $audit->record('audit.chain_break_detected', 'compliance', meta: ['breaks' => $result['breaks']]);
    $errors->report(new RuntimeException('Audit hash chain verification found '.count($result['breaks']).' break(s).'), 'critical');

    return 1;
})->purpose('Verify the audit log hash chains and report breaks');

Artisan::command('security:prune', function (): int {
    $cutoff = now()->subDays(90)->format('Y-m-d H:i:s.u');
    $deleted = DB::table('login_attempts')->where('attempted_at', '<', $cutoff)->delete();
    $this->info("Pruned {$deleted} login attempt(s).");

    return 0;
})->purpose('Remove expired security bookkeeping rows');

Schedule::command('outbox:relay')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('audit:verify')->dailyAt('02:10')->withoutOverlapping()->onOneServer();
Schedule::command('audit:verify --full')->weeklyOn(0, '03:10')->withoutOverlapping()->onOneServer();
Schedule::command('security:prune')->dailyAt('03:40')->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();

Artisan::command('setup:token', function (App\Modules\Setup\SetupState $state): int {
    if ($state->isComplete()) {
        $this->error('Setup is already complete; the wizard is locked.');

        return 1;
    }
    $token = $state->issueToken();
    $this->line('Setup token (enter it in the setup wizard; any earlier token is now invalid):');
    $this->info($token);

    return 0;
})->purpose('Issue the one-time token that unlocks the first-run setup wizard');
