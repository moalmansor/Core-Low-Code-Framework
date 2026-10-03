<?php

declare(strict_types=1);

namespace App\Modules\Setup\Http\Middleware;

use App\Modules\Setup\SetupState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The wizard is locked permanently once setup completes (specification §2):
 * every setup endpoint then answers 404. While it is open, calls other than
 * the status check must carry the setup token.
 */
final class SetupOpen
{
    public const TOKEN_HEADER = 'X-Setup-Token';

    public function __construct(private readonly SetupState $state) {}

    public function handle(Request $request, Closure $next, string $tokenRequired = 'token'): Response
    {
        abort_if($this->state->isComplete(), 404);
        if ($tokenRequired === 'token' && ! $this->state->checkToken($request->headers->get(self::TOKEN_HEADER))) {
            return response()->json(['message' => __('ui.setup.invalid_token'), 'code' => 'invalid_setup_token'], 403);
        }

        return $next($request);
    }
}
