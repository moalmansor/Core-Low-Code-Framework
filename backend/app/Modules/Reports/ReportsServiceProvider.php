<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Reports\Models\Report;
use Illuminate\Support\ServiceProvider;

/** Reports and dashboards: their names and descriptions are translatable (ADR-0053). */
final class ReportsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(TranslatableRegistry::class);
        $registry->register('report', Report::class, ['name', 'description'], 'translations.type.report');
        $registry->register('dashboard', Dashboard::class, ['name', 'description'], 'translations.type.dashboard');
    }
}
