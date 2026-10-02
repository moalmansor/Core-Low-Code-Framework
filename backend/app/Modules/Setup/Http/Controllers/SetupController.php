<?php

declare(strict_types=1);

namespace App\Modules\Setup\Http\Controllers;

use App\Modules\Access\AccessGuard;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Mail\SettingsSmtpTransport;
use App\Modules\Core\Mail\TestMail;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Settings\SettingsRegistry;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\PasswordPolicy;
use App\Modules\Records\FileStore;
use App\Modules\Setup\SetupState;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * First-run setup wizard (specification §2). Nothing is written until the
 * final step, which applies every choice and creates the Super Admin in one
 * transaction and then locks the wizard for good.
 */
final class SetupController extends Controller
{
    private const SESSION_SECRET = 'setup.two_factor_secret';

    public function __construct(
        private readonly SetupState $state,
        private readonly SettingsService $settings,
        private readonly SettingsRegistry $registry,
    ) {}

    public function status(): JsonResponse
    {
        $defaults = [];
        foreach (['formats', 'calendar', 'mail', 'tenancy'] as $group) {
            foreach ($this->registry->group($group) as $key => $definition) {
                if (! $definition->secret) {
                    $defaults[$group][$key] = $this->settings->get($group, $key);
                }
            }
        }

        return response()->json(['data' => [
            'completed' => false,
            'token_issued' => $this->state->hasToken(),
            'locales' => Locale::query()->orderBy('sort_order')->get(['code', 'native_name', 'direction', 'is_enabled', 'is_default']),
            'defaults' => $defaults,
            'password_policy' => $this->settings->publicGroup('security'),
        ]]);
    }

    public function verifyToken(Request $request): Response|JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);
        if (! $this->state->checkToken($data['token'])) {
            return response()->json(['message' => __('ui.setup.invalid_token'), 'code' => 'invalid_setup_token'], 422);
        }

        return response()->noContent();
    }

    /** Send a test e-mail with the SMTP values entered, before they are saved. */
    public function mailTest(Request $request): JsonResponse
    {
        $data = $request->validate($this->mailRules(true) + ['to' => ['required', 'email:rfc', 'max:255']]);
        try {
            $email = (new Email)
                ->from(new Address($data['mail']['from_address'], (string) ($data['mail']['from_name'] ?? '')))
                ->to($data['to'])
                ->subject(__('ui.mail.test.subject'))
                ->html((new TestMail)->render());
            (new Mailer(SettingsSmtpTransport::build($data['mail'])))->send($email);
        } catch (Throwable $e) {
            return response()->json(['message' => __('ui.settings.mail_test_failed'), 'detail' => mb_substr($e->getMessage(), 0, 500)], 422);
        }

        return response()->json(['data' => ['sent' => true]]);
    }

    /** Start TOTP enrollment for the Super Admin; the secret stays in the session. */
    public function twoFactor(Request $request, TwoFactorAuthenticationProvider $totp): JsonResponse
    {
        abort_unless($request->hasSession(), 400, __('ui.errors.session_required'));
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $secret = $totp->generateSecretKey((int) config('fortify-options.two-factor-authentication.secret-length', 32));
        $request->session()->put(self::SESSION_SECRET, encrypt($secret));
        $issuer = (string) ($request->input('issuer') ?: config('app.name'));
        $url = $totp->qrCodeUrl(mb_substr($issuer, 0, 64), $data['email'], $secret);
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39))),
            new SvgImageBackEnd,
        )))->writeString($url);

        return response()->json(['data' => [
            'secret' => $secret,
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ]]);
    }

    public function uploadBranding(Request $request, string $kind, FileStore $files): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);
        $file = $files->storeImage($request->file('file'), 'branding');

        return response()->json(['data' => ['uuid' => $file->uuid, 'kind' => $kind]]);
    }

    public function complete(Request $request, TwoFactorAuthenticationProvider $totp, PasswordPolicy $policy, TenantContext $tenant, AccessGuard $guard, AuditWriter $audit, Translator $translator): JsonResponse
    {
        $locales = Locale::query()->pluck('code')->all();
        $data = $request->validate([
            'system_name' => ['required', 'array'],
            'system_name.*' => ['nullable', 'string', 'max:120'],
            'default_locale' => ['required', Rule::in($locales)],
            'enabled_locales' => ['required', 'array', 'min:1'],
            'enabled_locales.*' => ['string', Rule::in($locales)],
            'logo_file' => ['nullable', 'uuid'],
            'favicon_file' => ['nullable', 'uuid'],
            'formats' => ['required', 'array'],
            'calendar' => ['required', 'array'],
            'tenancy' => ['required', 'array'],
            'mail' => ['nullable', 'array'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email:rfc', 'max:255'],
            'admin.password' => ['required', 'string'],
            'admin.password_confirmation' => ['required', 'string'],
            'two_factor_code' => ['required', 'string', 'max:16'],
        ]);
        if (! in_array($data['default_locale'], $data['enabled_locales'], true)) {
            throw ValidationException::withMessages(['enabled_locales' => __('ui.setup.default_locale_must_be_enabled')]);
        }
        if (trim((string) ($data['system_name'][$data['default_locale']] ?? '')) === '') {
            throw ValidationException::withMessages(['system_name.'.$data['default_locale'] => __('validation.required', ['attribute' => 'system name'])]);
        }
        Validator::make($data['admin'], ['password' => $policy->rules(null, $data['admin']['email'], $data['admin']['name'])])->validate();
        foreach (['formats', 'calendar', 'tenancy'] as $group) {
            $this->validateGroup($group, $data[$group]);
        }
        if (! empty($data['mail']['host'])) {
            $request->validate($this->mailRules(false));
        }
        abort_unless($request->hasSession(), 400, __('ui.errors.session_required'));
        $encrypted = $request->session()->get(self::SESSION_SECRET);
        $secret = is_string($encrypted) ? decrypt($encrypted) : null;
        if (! is_string($secret) || ! $totp->verify($secret, preg_replace('/\s+/', '', $data['two_factor_code']) ?? '')) {
            throw ValidationException::withMessages(['two_factor_code' => __('ui.setup.invalid_two_factor_code')]);
        }

        $recoveryCodes = Collection::times(8, static fn (): string => RecoveryCode::generate())->all();
        $user = DB::transaction(function () use ($data, $secret, $recoveryCodes, $policy, $tenant, $guard, $audit, $translator): User {
            // Serialize concurrent completions and re-check under the lock.
            DB::table('settings')->where('group', 'setup')->where('key', 'completed_at')->lockForUpdate()->first();
            $this->settings->forgetGroup('setup');
            abort_if($this->state->isComplete(), 404);

            $orgId = $tenant->platformOrganizationId();
            $org = Organization::query()->findOrFail($orgId);
            $org->forceFill(['default_locale' => $data['default_locale'], 'timezone' => $data['formats']['timezone'] ?? $org->timezone])->save();
            $org->setTranslations('name', array_filter($data['system_name'], static fn ($v): bool => is_string($v) && trim($v) !== ''));

            foreach (Locale::query()->get() as $locale) {
                $locale->forceFill([
                    'is_enabled' => in_array($locale->code, $data['enabled_locales'], true),
                    'is_default' => $locale->code === $data['default_locale'],
                ])->save();
            }
            $translator->flush();

            foreach (['formats', 'calendar', 'tenancy'] as $group) {
                foreach ($data[$group] as $key => $value) {
                    $this->settings->write($group, $key, $value);
                }
            }
            foreach (['logo_file', 'favicon_file'] as $key) {
                if (! empty($data[$key])) {
                    $this->settings->write('branding', $key, $data[$key]);
                }
            }
            if (! empty($data['mail']['host'])) {
                foreach ($data['mail'] as $key => $value) {
                    if ($this->registry->get('mail', $key) !== null) {
                        $this->settings->write('mail', $key, $value);
                    }
                }
            }

            $user = $guard->guarded(function () use ($data, $secret, $recoveryCodes, $policy): User {
                $user = new User;
                $user->forceFill([
                    'name' => $data['admin']['name'],
                    'email' => mb_strtolower($data['admin']['email']),
                    'status' => 'active',
                    'auth_source' => 'local',
                    'email_verified_at' => now(),
                    'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
                    'two_factor_confirmed_at' => now(),
                ])->save();
                $policy->apply($user, $data['admin']['password']);
                $role = Role::query()->where('key', 'super_admin')->firstOrFail();
                $user->roles()->attach($role->id, ['created_at' => now()->format('Y-m-d H:i:s.u')]);
                $user->preference()->create(['locale' => $data['default_locale']]);

                return $user;
            });

            $this->settings->write('setup', 'completed_at', now()->toIso8601String());
            $this->state->discardToken();
            $audit->record('setup.completed', 'config', meta: [
                'default_locale' => $data['default_locale'],
                'enabled_locales' => $data['enabled_locales'],
                'tenancy_mode' => $data['tenancy']['mode'] ?? null,
                'mail_configured' => ! empty($data['mail']['host']),
            ], actorUserId: $user->id, subjectUserId: $user->id);

            return $user;
        });

        $request->session()->forget(self::SESSION_SECRET);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        DB::table('sessions')->where('id', $request->session()->getId())->update(['two_factor_passed_at' => now()->format('Y-m-d H:i:s.u')]);

        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]], 201);
    }

    /** @param array<string, mixed> $values */
    private function validateGroup(string $group, array $values): void
    {
        $rules = [];
        foreach ($this->registry->group($group) as $key => $definition) {
            $rules[$key] = $definition->rules;
        }
        $unknown = array_diff(array_keys($values), array_keys($rules));
        if ($unknown !== []) {
            throw ValidationException::withMessages([$group => __('ui.settings.unknown_keys', ['keys' => implode(', ', $unknown)])]);
        }
        $merged = [];
        foreach ($rules as $key => $_) {
            $merged[$key] = array_key_exists($key, $values) ? $values[$key] : $this->settings->get($group, $key);
        }
        Validator::make($merged, $rules)->validate();
    }

    /** @return array<string, mixed> */
    private function mailRules(bool $prefixRequired): array
    {
        return [
            'mail' => [$prefixRequired ? 'required' : 'nullable', 'array'],
            'mail.host' => ['required', 'string', 'max:255'],
            'mail.port' => ['required', 'integer', 'between:1,65535'],
            'mail.encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'mail.username' => ['nullable', 'string', 'max:255'],
            'mail.password' => ['nullable', 'string', 'max:1024'],
            'mail.from_address' => ['required', 'email:rfc', 'max:255'],
            'mail.from_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
