<?php

declare(strict_types=1);

namespace App\Modules\DataQuality;

use Illuminate\Support\ServiceProvider;

/** The duplicate guard keeps, for one request, whether the person saving confirmed a duplicate warning. */
final class DataQualityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(DuplicateGuard::class);
    }
}
