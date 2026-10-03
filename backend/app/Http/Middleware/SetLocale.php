<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Chooses the request locale (specification §3 RTL/LTR): the signed-in user's
 * preference, then the `X-Locale` header the SPA sends, then Accept-Language,
 * then the default locale. Only enabled locales are accepted.
 */
final class SetLocale
{
    public const HEADER = 'X-Locale';

    public function __construct(private readonly Translator $translator) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $enabled = $this->translator->enabledLocales();
            $default = $this->translator->defaultLocale();
        } catch (Throwable) {
            // Before the first migration there is no locale table yet.
            return $next($request);
        }
        $candidates = [];
        $user = $request->hasSession() ? $request->user() : null;
        if ($user instanceof User) {
            $candidates[] = $user->preference?->locale;
        }
        $candidates[] = $request->headers->get(self::HEADER);
        foreach ($request->getLanguages() as $language) {
            $candidates[] = strtolower(substr($language, 0, 2));
        }
        $locale = $default;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $enabled, true)) {
                $locale = $candidate;
                break;
            }
        }
        App::setLocale($locale);
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
