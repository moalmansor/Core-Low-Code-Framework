<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\PasswordPolicy;
use App\Modules\Identity\Sessions\SessionRevoker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/** The signed-in user's own profile, permissions, and sessions (§5). */
final class MeController extends Controller
{
    public function show(AccessResolver $resolver, PasswordPolicy $policy): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $permissions = array_keys(array_filter($resolver->effective($user)));

        return response()->json(['data' => [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'auth_source' => $user->auth_source,
            'job_title' => $user->job_title,
            'roles' => $user->roleKeys(),
            'permissions' => $permissions,
            'two_factor' => [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'required' => $user->requiresTwoFactor(),
                'pending_confirmation' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            ],
            'password_expired' => $policy->isExpired($user),
            'preferences' => $this->presentPreferences($user),
        ]]);
    }

    /** Personal preferences: language (and so direction), time zone, calendar, theme. */
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $enabled = app(\App\Modules\Core\I18n\Translator::class)->enabledLocales();
        $data = $request->validate([
            'locale' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::in($enabled)],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'calendar' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::in(['gregorian', 'hijri', 'both'])],
            'digits' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::in(['western', 'arabic_indic'])],
            'date_format' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[yMdHhmsaEG\/\-\.\s,]+$/'],
            'theme_mode' => ['sometimes', \Illuminate\Validation\Rule::in(['light', 'dark', 'system'])],
            'density' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::in(['compact', 'normal', 'comfortable'])],
        ]);
        $preference = $user->preference()->firstOrNew();
        $preference->fill($data)->save();
        $user->setRelation('preference', $preference);

        return response()->json(['data' => $this->presentPreferences($user)]);
    }

    /** @return array<string, mixed> */
    private function presentPreferences(User $user): array
    {
        $p = $user->preference;

        return [
            'locale' => $p?->locale,
            'timezone' => $p?->timezone,
            'calendar' => $p?->calendar,
            'digits' => $p?->digits,
            'date_format' => $p?->date_format,
            'theme_mode' => $p->theme_mode ?? 'system',
            'density' => $p?->density,
        ];
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{3,32}$/'],
        ]);
        $user->fill($data)->save();

        return response()->json(['data' => ['name' => $user->name, 'phone' => $user->phone]]);
    }

    public function sessions(): JsonResponse
    {
        $current = Session::getId();
        $rows = DB::table('sessions')->where('user_id', Auth::id())->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity', 'created_at', 'absolute_expires_at', 'two_factor_passed_at']);

        return response()->json(['data' => $rows->map(static fn ($s): array => [
            // Session IDs are secrets; expose only a keyed hash as the handle.
            'handle' => hash_hmac('sha256', (string) $s->id, (string) config('app.key')),
            'ip_address' => $s->ip_address,
            'user_agent' => $s->user_agent,
            'last_activity' => date(DATE_ATOM, (int) $s->last_activity),
            'created_at' => $s->created_at,
            'expires_at' => $s->absolute_expires_at,
            'current' => hash_equals((string) $s->id, (string) $current),
        ])->values()]);
    }

    public function revokeSession(string $handle, SessionRevoker $revoker): JsonResponse
    {
        $id = DB::table('sessions')->where('user_id', Auth::id())->pluck('id')
            ->first(static fn ($sid): bool => hash_equals(hash_hmac('sha256', (string) $sid, (string) config('app.key')), $handle));
        abort_if($id === null, 404);
        abort_if($id === Session::getId(), 422, __('ui.sessions.cannot_revoke_current'));
        $revoker->revoke((string) $id, (int) Auth::id());
        app(\App\Modules\Audit\AuditWriter::class)->record('auth.session_revoked', 'auth', objectType: 'user', objectId: (int) Auth::id());

        return response()->json(null, 204);
    }

    public function revokeOtherSessions(SessionRevoker $revoker): JsonResponse
    {
        $count = $revoker->revokeAllFor((int) Auth::id(), exceptCurrent: true);
        app(\App\Modules\Audit\AuditWriter::class)->record('auth.sessions_revoked', 'auth', objectType: 'user', objectId: (int) Auth::id(), meta: ['count' => $count]);

        return response()->json(['data' => ['revoked' => $count]]);
    }
}
