<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\Core\Actors\ExternalActor;
use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\External\Models\ExternalForm;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** External access (ADR-0058): the acting external user, translatable texts and the visitors' rate limit. */
final class ExternalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ExternalActor::class);
    }

    public function boot(): void
    {
        $this->app->make(TranslatableRegistry::class)->register('external_form', ExternalForm::class, ['title', 'intro', 'success_message'], 'translations.type.external_form');
        // Every visitor address together: pages, codes, submissions, links and the portal.
        RateLimiter::for('external', static fn (Request $request): Limit => Limit::perMinute(60)->by('external:'.$request->ip()));
    }
}
