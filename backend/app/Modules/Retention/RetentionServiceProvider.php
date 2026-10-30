<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Retention\Models\RetentionPolicy;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\ServiceProvider;

/** Retention, legal holds, personal data and storage quotas (§4.26): the policies' translatable names and their schedule. */
final class RetentionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(TranslatableRegistry::class)->register('retention_policy', RetentionPolicy::class, ['name'], 'translations.type.retention_policy');
        // Scheduled runs of retention policies.
        $this->app->make(ScheduledTasks::class)->handle('retention', static fn (object $task) => app(RetentionEngine::class)->scheduled($task));
    }
}
