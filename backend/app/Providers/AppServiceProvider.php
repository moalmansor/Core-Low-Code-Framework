<?php

namespace App\Providers;

use App\Infrastructure\Crypto\DataKeys;
use App\Modules\Audit\AuditWriter;
use App\Support\BatchMemo;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
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

        // The audit writer remembers a pinned chain's head only while its transaction holds the head's lock.
        Event::listen(TransactionRolledBack::class, static fn () => AuditWriter::forgetHead());
        Event::listen(TransactionCommitted::class, static function (TransactionCommitted $e): void {
            if ($e->connection->transactionLevel() === 0) {
                AuditWriter::forgetHead();
            }
        });
    }
}
