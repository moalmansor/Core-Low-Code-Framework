<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Integrations\Models\ExternalDataSource;
use Illuminate\Support\ServiceProvider;

/** External data sources and sync jobs (ADR-0057): their translatable names. */
final class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(TranslatableRegistry::class)->register('external_data_source', ExternalDataSource::class, ['name'], 'translations.type.external_data_source');
    }
}
