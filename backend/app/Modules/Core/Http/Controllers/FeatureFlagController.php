<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Flags\FeatureFlag;
use App\Modules\Core\Flags\FeatureFlags;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Feature flags (specification §4.36, ADR-0062), managed with "Manage feature flags". */
final class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureFlags $flags) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_feature_flags');

        return response()->json(['data' => FeatureFlag::query()->orderBy('key')->get()->map(fn (FeatureFlag $f) => $this->flags->present($f))->values()->all(),
            'capabilities' => array_map(static fn ($k) => ['key' => $k, 'label' => __("platform.capability.{$k}")], array_keys(FeatureFlags::CAPABILITIES))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_feature_flags');
        $f = $this->flags->save(null, $this->flags->check((array) $request->input('flag', []), null), $this->user());

        return response()->json(['data' => $this->flags->present($f)], 201);
    }

    public function update(Request $request, string $flag): JsonResponse
    {
        Gate::authorize('system.manage_feature_flags');
        $f = $this->find($flag);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->flags->present($f)['hash'], (string) $request->input('base_hash')), 409, __('platform.flag_changed_meanwhile'));
        $f = $this->flags->save($f, $this->flags->check((array) $request->input('flag', []), $f), $this->user());

        return response()->json(['data' => $this->flags->present($f)]);
    }

    public function destroy(string $flag): JsonResponse
    {
        Gate::authorize('system.manage_feature_flags');
        $this->flags->delete($this->find($flag), $this->user());

        return response()->json(null, 204);
    }

    private function find(string $uuid): FeatureFlag
    {
        abort_unless(Str::isUuid($uuid), 404);

        return FeatureFlag::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
