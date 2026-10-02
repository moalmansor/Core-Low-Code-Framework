<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers and the Content Security Policy (specification §5,
 * architecture §19.1). HTML responses get a nonce-based policy without
 * 'unsafe-inline' or 'unsafe-eval'; every other response gets a policy that
 * loads nothing.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::cspNonce() ?? Vite::useCspNonce();
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->remove('X-Powered-By');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $isHtml = str_contains((string) $headers->get('Content-Type'), 'text/html');
        $headers->set('Content-Security-Policy', $isHtml ? $this->htmlPolicy($nonce, $request) : "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        // API answers are never cached unless a controller marked them public
        // (interface string catalogs, branding images).
        if (str_starts_with($request->path(), 'api/') && ! str_contains((string) $headers->get('Cache-Control'), 'public')) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function htmlPolicy(string $nonce, Request $request): string
    {
        $script = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];
        if (Vite::isRunningHot()) {
            // Local development only: the Vite dev server serves modules and HMR.
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $script[] = $hot;
            $connect[] = $hot;
            $connect[] = (string) preg_replace('/^http/', 'ws', $hot);
        }
        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "manifest-src 'self'",
        ];
        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
