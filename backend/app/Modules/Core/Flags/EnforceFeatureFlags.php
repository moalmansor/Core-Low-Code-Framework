<?php

declare(strict_types=1);

namespace App\Modules\Core\Flags;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature flags on every signed-in request (ADR-0062): a form, action or
 * page released only to others does not exist for this person (404); a
 * capability they have not been given yet is refused (403).
 */
final class EnforceFeatureFlags
{
    public function __construct(private readonly FeatureFlags $flags) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $refusal = $user instanceof User ? $this->flags->refusal($user, $request) : null;
        if ($refusal === null) {
            return $next($request);
        }
        // Hidden targets do not exist for this person; a capability says why it is refused.
        abort_if($refusal[1] === 404, 404);

        return response()->json(['message' => __('platform.feature_off'), 'code' => 'feature_off'], 403);
    }
}
