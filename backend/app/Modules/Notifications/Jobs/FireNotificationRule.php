<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Notifier;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * A notification rule with a delay: fired on the `default` queue when the
 * delay is over. The rule and its condition are read again then, so a rule
 * switched off or a condition no longer true sends nothing.
 */
final class FireNotificationRule implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $ruleId, public readonly string $formUuid, public readonly int $recordId) {}

    public function handle(Notifier $notifier, FormRuntimes $runtimes): void
    {
        $rule = DB::table('notification_rules')->where('id', $this->ruleId)->where('is_active', true)->first();
        $rt = $runtimes->forUuid($this->formUuid);
        if ($rule !== null && $rt !== null) {
            $notifier->deliver($rule, $rt, $this->recordId);
        }
    }
}
