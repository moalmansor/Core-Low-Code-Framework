<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every query of an authenticated request runs in the user's organization (architecture §19.17). */
final class SetTenantFromUser
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->tenant->set((int) $user->organization_id);
        }

        return $next($request);
    }
}
