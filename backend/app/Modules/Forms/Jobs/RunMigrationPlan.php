<?php

declare(strict_types=1);

namespace App\Modules\Forms\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Publishing\PublishService;
use App\Modules\Schema\Models\MigrationPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Executes a confirmed migration plan on the `schema` queue. While another
 * publish holds a related form the job is released and retried; the admin
 * sees who blocks it (publish_locks `waiting` rows).
 */
final class RunMigrationPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $timeout = 3600;

    public function __construct(public readonly int $planId)
    {
        $this->onQueue('schema');
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_schema');
        }
    }

    public function retryUntil(): Carbon
    {
        return Carbon::now()->addHours(6);
    }

    public function handle(PublishService $publisher): void
    {
        $plan = MigrationPlan::query()->withoutGlobalScopes()->find($this->planId);
        if ($plan === null || ! in_array($plan->status, ['pending', 'locked'], true)) {
            return;
        }
        app(TenantContext::class)->set((int) $plan->organization_id);
        if ($publisher->execute($plan) === 'waiting') {
            $this->release(app()->runningUnitTests() ? 0 : 30);
        }
    }
}
