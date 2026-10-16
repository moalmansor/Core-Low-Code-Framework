<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Issues, checks and revokes personal API tokens (specification §4.22,
 * ADR-0050).
 *
 * - A token is `lcf_` followed by 48 random characters; the table holds its
 *   SHA-256, so a stolen database cannot be used to call the API.
 * - It lasts at most `api.token_max_days` and is refused once revoked or
 *   expired, when its owner is no longer active, from an address outside its
 *   allow-list, and whenever the API is switched off.
 * - Its abilities name forms and read or write; each must be within what the
 *   owner may do when the token is made, and every request is checked again
 *   against the owner's permissions as they are then.
 * - Tokens authenticate the data API only (`/api/v1/data`, the OpenAPI
 *   document); every other endpoint needs a signed-in session.
 */
final class ApiTokens
{
    public const PREFIX = 'lcf_';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{name: string, abilities: list<string>, expires_in_days?: int|null, ip_allowlist?: list<string>|null, rate_limit_per_minute?: int|null}  $data
     * @return array{token: ApiToken, secret: string}
     */
    public function issue(User $owner, array $data): array
    {
        $abilities = $this->checkAbilities($owner, $data['abilities']);
        $max = (int) $this->settings->get('api', 'token_max_days');
        $days = (int) ($data['expires_in_days'] ?? $max);
        if ($days < 1 || $days > $max) {
            throw ValidationException::withMessages(['expires_in_days' => __('platform.token_days', ['max' => $max])]);
        }
        foreach ($data['ip_allowlist'] ?? [] as $i => $ip) {
            if (! $this->validCidr((string) $ip)) {
                throw ValidationException::withMessages(["ip_allowlist.{$i}" => __('platform.bad_ip')]);
            }
        }
        $secret = self::PREFIX.Str::random(48);
        $token = new ApiToken;
        $token->forceFill([
            'organization_id' => $owner->organization_id,
            'tokenable_type' => 'user', 'tokenable_id' => $owner->id,
            'name' => $data['name'], 'token' => hash('sha256', $secret), 'abilities' => $abilities,
            'ip_allowlist' => ($data['ip_allowlist'] ?? []) === [] ? null : array_values($data['ip_allowlist'] ?? []),
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? null,
            'expires_at' => now()->addDays($days), 'created_by' => $owner->id,
        ])->save();
        $this->audit->record('api_token.created', 'security', null, 'api_token', $token->id, ['name' => $token->name, 'abilities' => $abilities, 'expires_at' => $token->expires_at?->toIso8601String()], $owner->id);

        return ['token' => $token, 'secret' => $secret];
    }

    public function revoke(ApiToken $token, User $by): void
    {
        if ($token->revoked_at !== null) {
            return;
        }
        $token->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('api_token.revoked', 'security', null, 'api_token', $token->id, ['name' => $token->name, 'owner' => $token->tokenable_id], $by->id);
    }

    /** The owner of a valid bearer token for this request, or null. */
    public function authenticate(Request $request): ?User
    {
        $secret = (string) $request->bearerToken();
        if (! str_starts_with($secret, self::PREFIX) || strlen($secret) > 128) {
            return null;
        }
        /** @var ApiToken|null $token */
        $token = ApiToken::query()->withoutGlobalScope('organization')->where('token', hash('sha256', $secret))->first();
        if ($token === null || ! $token->active() || $token->tokenable_type !== 'user') {
            return null;
        }
        if ($token->ip_allowlist !== null && $token->ip_allowlist !== [] && ! IpUtils::checkIp((string) $request->ip(), $token->ip_allowlist)) {
            return null;
        }
        /** @var User|null $user */
        $user = User::query()->withoutGlobalScopes()->whereKey($token->tokenable_id)->first();
        if ($user === null || (int) $user->organization_id !== (int) $token->organization_id || $user->status !== 'active' || $user->trashed()) {
            return null;
        }
        // The API can be switched off per organization (System Settings › API and webhooks).
        app(TenantContext::class)->set((int) $token->organization_id);
        if (! (bool) $this->settings->get('api', 'enabled')) {
            return null;
        }
        // Last use is kept to the minute: one write per token per minute at most.
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            DB::table('api_tokens')->where('id', $token->id)->update(['last_used_at' => now()->format('Y-m-d H:i:s.u')]);
        }
        $request->attributes->set('lcf.api_token', $token);

        return $user;
    }

    /** The token that authenticated this request, if any. */
    public static function current(?Request $request = null): ?ApiToken
    {
        $token = ($request ?? request())->attributes->get('lcf.api_token');

        return $token instanceof ApiToken ? $token : null;
    }

    /**
     * The abilities the owner may give a token: read for every form they may
     * view, write for every form they may create, edit or delete records in.
     *
     * @return list<array{form: string, key: string, name: string, read: bool, write: bool}>
     */
    public function grantable(User $owner): array
    {
        $out = [];
        foreach (Form::query()->whereIn('state', ['published', 'schema_inconsistent'])->orderBy('key')->get() as $form) {
            $read = $this->access->allows($owner, "form.{$form->uuid}.view");
            if (! $read) {
                continue;
            }
            $write = false;
            foreach (['create', 'edit', 'delete'] as $a) {
                $write = $write || $this->access->allows($owner, "form.{$form->uuid}.{$a}");
            }
            $out[] = ['form' => strtolower($form->uuid), 'key' => (string) $form->key, 'name' => (string) ($form->translate('name') ?? Translator::humanize((string) $form->key)), 'read' => true, 'write' => $write];
        }

        return $out;
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function checkAbilities(User $owner, array $abilities): array
    {
        $grantable = [];
        foreach ($this->grantable($owner) as $g) {
            $grantable["form:{$g['form']}:read"] = true;
            if ($g['write']) {
                $grantable["form:{$g['form']}:write"] = true;
            }
        }
        $out = [];
        foreach (array_values(array_unique(array_map('strtolower', $abilities))) as $i => $ability) {
            if (! isset($grantable[$ability])) {
                throw ValidationException::withMessages(["abilities.{$i}" => __('platform.ability_not_yours')]);
            }
            $out[] = $ability;
        }
        if ($out === []) {
            throw ValidationException::withMessages(['abilities' => __('platform.abilities_required')]);
        }

        return $out;
    }

    private function validCidr(string $ip): bool
    {
        [$addr, $bits] = array_pad(explode('/', $ip, 2), 2, null);
        if (filter_var($addr, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($bits === null) {
            return true;
        }
        $max = str_contains((string) $addr, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits >= 0 && (int) $bits <= $max;
    }
}
