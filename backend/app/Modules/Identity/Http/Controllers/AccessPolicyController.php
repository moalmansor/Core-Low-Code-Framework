<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\AccessPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\AccessPolicies;
use App\Modules\Identity\Policies\TrustedDevices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Access policies (specification §4.36, ADR-0060), managed with "Manage
 * access policies"; and the trusted devices of the signed-in user.
 */
final class AccessPolicyController extends Controller
{
    public function __construct(private readonly AccessPolicies $policies, private readonly TrustedDevices $devices) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_access_policies');

        return response()->json(['data' => AccessPolicy::query()->orderBy('key')->get()->map(fn (AccessPolicy $p) => $this->policies->present($p))->values()->all()]);
    }

    public function show(string $policy): JsonResponse
    {
        Gate::authorize('system.manage_access_policies');

        return response()->json(['data' => $this->policies->present($this->find($policy))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_access_policies');
        $p = $this->policies->save(null, $this->policies->check((array) $request->input('policy', []), null), $this->user(), $request);

        return response()->json(['data' => $this->policies->present($p)], 201);
    }

    public function update(Request $request, string $policy): JsonResponse
    {
        Gate::authorize('system.manage_access_policies');
        $p = $this->find($policy);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->policies->present($p)['hash'], (string) $request->input('base_hash')), 409, __('identity.changed_meanwhile'));
        $p = $this->policies->save($p, $this->policies->check((array) $request->input('policy', []), $p), $this->user(), $request);

        return response()->json(['data' => $this->policies->present($p)]);
    }

    public function destroy(string $policy): JsonResponse
    {
        Gate::authorize('system.manage_access_policies');
        $this->policies->delete($this->find($policy), $this->user());

        return response()->json(null, 204);
    }

    /** The signed-in user's trusted devices. */
    public function devices(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->devices->list($this->user(), $request)]);
    }

    public function revokeDevice(int $device): JsonResponse
    {
        abort_unless($this->devices->revoke($this->user(), $device, $this->user()->id), 404);

        return response()->json(null, 204);
    }

    private function find(string $uuid): AccessPolicy
    {
        abort_unless(Str::isUuid($uuid), 404);

        return AccessPolicy::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
