<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordNotExpired
{
    public function __construct(private readonly PasswordPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $this->policy->isExpired($user)) {
            return response()->json(['message' => __('ui.auth.password_expired'), 'code' => 'password_expired'], 403);
        }

        return $next($request);
    }
}
