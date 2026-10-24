<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Impersonation\Impersonation;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Starting and stopping an impersonation (specification §4.36, ADR-0061). */
final class ImpersonationController extends Controller
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function start(Request $request, User $user): JsonResponse
    {
        Gate::authorize('system.impersonate_users');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'minutes' => ['required', 'integer'],
        ]);
        /** @var User $actor */
        $actor = Auth::user();
        $s = $this->impersonation->start($actor, $user, trim($data['reason']), (int) $data['minutes'], $request);

        return response()->json(['data' => ['expires_at' => Carbon::parse($s->expires_at, 'UTC')->toIso8601ZuluString()]], 201);
    }

    public function stop(Request $request): JsonResponse
    {
        $s = $this->impersonation->current($request);
        abort_if($s === null, 404);
        $this->impersonation->stop($s, $request, 'manual');

        return response()->json(null, 204);
    }
}
