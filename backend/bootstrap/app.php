<?php

declare(strict_types=1);

use App\Http\Middleware\NormalizeDocumentInput;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Http\Middleware\AssignCorrelationId;
use App\Modules\Identity\Http\Middleware\EnforceSessionPolicy;
use App\Modules\Identity\Http\Middleware\EnsureActiveUser;
use App\Modules\Identity\Http\Middleware\EnsurePasswordNotExpired;
use App\Modules\Identity\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Modules\Identity\Http\Middleware\SetTenantFromUser;
use App\Modules\Monitoring\ErrorReporter;
use App\Modules\Setup\Http\Middleware\SetupOpen;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The SPA and the API share an origin; Sanctum authenticates the SPA
        // with the session cookie and enforces CSRF (specification §5).
        $middleware->statefulApi();
        $middleware->prepend([AssignCorrelationId::class, SecurityHeaders::class]);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('api', SetLocale::class);
        $middleware->encryptCookies();
        // Metadata documents and expressions are normalised by NormalizeDocumentInput,
        // which leaves expression nodes untouched (ADR-0029).
        $verbatim = static fn (Request $r): bool => NormalizeDocumentInput::applies($r);
        $middleware->appendToGroup('api', NormalizeDocumentInput::class);
        // Separators may legitimately be a single space.
        $middleware->trimStrings(except: [$verbatim, 'number_format.group', 'number_format.decimal', 'thousands_separator', 'decimal_separator', 'formats.thousands_separator', 'formats.decimal_separator']);
        $middleware->convertEmptyStringsToNull(except: [$verbatim]);

        // Signed-in requests: active account, tenant, idle/absolute timeouts.
        $middleware->group('lcf.session', [
            EnsureActiveUser::class,
            SetTenantFromUser::class,
            EnforceSessionPolicy::class,
            'throttle:api',
        ]);
        // Everything else additionally needs 2FA enrollment where the role
        // requires it, and an unexpired password.
        $middleware->group('lcf.secure', [
            'lcf.session',
            EnsureTwoFactorEnrolled::class,
            EnsurePasswordNotExpired::class,
        ]);
        $middleware->alias(['lcf.setup' => SetupOpen::class]);
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => $request->expectsJson() ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'code', 'recovery_code', 'two_factor_code', 'token']);

        // Every unexpected exception goes to Error Monitoring (secondary sink
        // first, then the database); the user sees a reference ID only.
        $exceptions->report(static function (Throwable $e): bool {
            $reference = app(ErrorReporter::class)->report($e);
            app()->instance('lcf.error_reference', $reference);

            return false;
        });

        $exceptions->render(static function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => __('ui.auth.unauthenticated'), 'code' => 'unauthenticated'], 401);
            }
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return null; // framework rendering: message only, no internals
            }
            if ($e instanceof ValidationException || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException) {
                return null;
            }
            if (config('app.debug')) {
                return null;
            }
            $reference = app()->bound('lcf.error_reference') ? (string) app('lcf.error_reference') : null;

            return response()->json([
                // The reference is part of the message, so every place that shows the message shows it too.
                'message' => $reference === null || $reference === '' ? __('ui.errors.unexpected') : __('ui.errors.unexpected_with_reference', ['reference' => $reference]),
                'reference' => $reference,
                'correlation_id' => app(CorrelationId::class)->get(),
            ], $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);
        });
    })->create();
