<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Middleware;

use App\Modules\Experience\Maintenance;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * System-wide maintenance mode (architecture §19.17): signed-in requests
 * answer 503 with the maintenance message, except for people who may switch
 * maintenance mode. Who is signed in, signing out and the maintenance status
 * itself stay reachable, so the interface can explain what is going on.
 */
final class SystemMaintenance
{
    private const OPEN = ['api/v1/me', 'api/v1/maintenance', 'api/v1/auth/*', 'api/v1/bootstrap', 'api/v1/i18n/*'];

    public function __construct(private readonly Maintenance $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $request->is(...self::OPEN) && $this->maintenance->blocksSystem($user)) {
            $system = $this->maintenance->system();

            return response()->json(['message' => $system['message'], 'code' => 'maintenance', 'until' => $system['until']], 503);
        }

        return $next($request);
    }
}
