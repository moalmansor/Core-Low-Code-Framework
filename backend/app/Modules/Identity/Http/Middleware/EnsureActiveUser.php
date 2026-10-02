<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ends the session of users who were suspended, disabled, or deleted meanwhile. */
final class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ($user->status !== 'active' || $user->trashed())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => __('ui.auth.inactive'), 'code' => 'account_inactive'], 401);
        }

        return $next($request);
    }
}
