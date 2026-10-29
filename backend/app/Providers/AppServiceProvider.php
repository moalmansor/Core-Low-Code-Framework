<?php

namespace App\Providers;

use App\Infrastructure\Crypto\DataKeys;
use App\Support\BatchMemo;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Data keys are cached per request or job, so a rotation elsewhere is seen by the next one.
        $this->app->scoped(DataKeys::class);
        $this->app->singleton(BatchMemo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Reverse proxies whose X-Forwarded-* headers are trusted (client IP,
        // scheme). Configured per deployment; never "*" by default.
        TrustProxies::at(config('app.trusted_proxies'));
    }
}
