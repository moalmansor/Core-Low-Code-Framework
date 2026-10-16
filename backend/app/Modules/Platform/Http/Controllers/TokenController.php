<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\ApiTokens;
use App\Modules\Platform\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Personal API tokens (specification §4.22, ADR-0050): each person makes,
 * lists and revokes their own; holders of "Manage integrations" see every
 * token of the organization and can revoke any. The secret is returned once,
 * by the request that makes the token.
 */
final class TokenController extends Controller
{
    public function __construct(private readonly ApiTokens $tokens) {}

    public function mine(): JsonResponse
    {
        $rows = ApiToken::query()->where('tokenable_type', 'user')->where('tokenable_id', $this->user()->id)->orderByDesc('id')->get();

        return response()->json(['data' => $rows->map(fn (ApiToken $t) => $this->present($t))->all(), 'meta' => ['grantable' => $this->tokens->grantable($this->user())]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1', 'max:200'],
            'abilities.*' => ['string', 'regex:/^form:([0-9a-fA-F-]{36}|\*):(read|write)$/'],
            'expires_in_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'ip_allowlist' => ['sometimes', 'nullable', 'array', 'max:20'],
            'ip_allowlist.*' => ['string', 'max:64'],
            'rate_limit_per_minute' => ['sometimes', 'nullable', 'integer', 'between:1,10000'],
        ]);
        ['token' => $token, 'secret' => $secret] = $this->tokens->issue($this->user(), $data);

        return response()->json(['data' => $this->present($token) + ['secret' => $secret]], 201);
    }

    public function revokeMine(string $token): JsonResponse
    {
        $row = ApiToken::query()->where('uuid', strtolower($token))->where('tokenable_type', 'user')->where('tokenable_id', $this->user()->id)->firstOrFail();
        $this->tokens->revoke($row, $this->user());

        return response()->json(['data' => $this->present($row->refresh())]);
    }

    /** Every token of the organization, for the Integrations area. */
    public function all(): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $rows = ApiToken::query()->orderByDesc('id')->limit(500)->get();
        $names = DB::table('users')->whereIn('id', $rows->pluck('tokenable_id'))->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn (ApiToken $t) => $this->present($t) + ['owner' => (string) ($names[$t->tokenable_id] ?? '')])->all()]);
    }

    public function revoke(string $token): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $row = ApiToken::query()->where('uuid', strtolower($token))->firstOrFail();
        $this->tokens->revoke($row, $this->user());

        return response()->json(['data' => $this->present($row->refresh())]);
    }

    /** @return array<string, mixed> */
    private function present(ApiToken $t): array
    {
        return [
            'uuid' => $t->uuid, 'name' => $t->name, 'abilities' => $t->abilities, 'ip_allowlist' => $t->ip_allowlist ?? [],
            'rate_limit_per_minute' => $t->rate_limit_per_minute, 'created_at' => $t->created_at->toIso8601String(),
            'last_used_at' => $t->last_used_at?->toIso8601String(), 'expires_at' => $t->expires_at?->toIso8601String(),
            'revoked_at' => $t->revoked_at?->toIso8601String(), 'active' => $t->active(),
        ];
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
