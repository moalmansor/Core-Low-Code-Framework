<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Integrations\Models\ExternalDataSource;
use App\Modules\Integrations\Models\SyncJob;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\ServiceProvider;

/** External data sources and sync jobs (ADR-0057): their translatable names and the sync schedule. */
final class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(TranslatableRegistry::class);
        $registry->register('external_data_source', ExternalDataSource::class, ['name'], 'translations.type.external_data_source');
        $registry->register('sync_job', SyncJob::class, ['name'], 'translations.type.sync_job');
        // Scheduled runs of sync jobs.
        $this->app->make(ScheduledTasks::class)->handle('sync', static fn (object $task) => app(SyncJobs::class)->scheduled($task));
    }
}
