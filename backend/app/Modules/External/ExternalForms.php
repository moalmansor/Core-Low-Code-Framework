<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Actors\ExternalActor;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Experience\Models\Theme;
use App\Modules\Experience\Themes\Themes;
use App\Modules\External\Models\ExternalForm;
use App\Modules\External\Models\ExternalUser;
use App\Modules\Forms\Definition\ClientDefinition;
use App\Modules\Forms\FieldTypes\FieldTypeRegistry;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * External forms (specification §4.32, ADR-0058).
 *
 * - An administrator publishes a form at `/x/f/{slug}` with a title,
 *   introduction and success message per language, a theme, the status a
 *   submission starts in, and the fields it shows. Only fields that need no
 *   internal data can be shown: no files, no references to records, users,
 *   roles or departments, no choices loaded from the server, and nothing
 *   inside a repeating group or sub-form. A required field without a
 *   default must be shown, or no submission could ever pass.
 * - A submission goes through the same record pipeline as the UI, acting as
 *   the form's run-as user: permissions, field access, rules, validation,
 *   workflow, audit and the submission journal (source `external`). Only
 *   the shown fields are taken from the visitor; the run-as user's field
 *   access still applies on top. The signed-in external user, if any, is
 *   recorded on the record, the audit entries, the journal and the status
 *   history.
 * - Protections: open to anyone or only to signed-in external users with
 *   one of its roles, an optional password, a proof-of-work CAPTCHA, a limit
 *   per visitor address per hour, an opening and closing time, hours of the
 *   day and days of the week in the organization's time zone, a cap on the
 *   number of submissions, and email or SMS verification with a one-time
 *   code.
 * - The visitor never sees the run-as user, internal field names of hidden
 *   fields, or errors about fields the form does not show.
 */
final class ExternalForms
{
    public const MAX_TEXT = 4000;

    /** Field types never shown outside: they need files, internal records, server-only values or a third party. */
    private const HIDDEN_TYPES = ['password', 'hidden', 'signature', 'map_location', 'json', 'key_value', 'barcode', 'image_button', 'button', 'submit', 'reset'];

    private const HIDDEN_CATEGORIES = ['file', 'reference', 'action'];

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly Translator $translator,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly ClientDefinition $client,
        private readonly RecordPipeline $pipeline,
        private readonly TenantContext $tenant,
        private readonly SettingsService $settings,
        private readonly ProofOfWork $pow,
        private readonly VerificationCodes $codes,
        private readonly Throttles $throttles,
        private readonly ExternalActor $actor,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * The fields of a form that can be shown outside, in definition order.
     *
     * @return list<array{uuid: string, key: string, type: string, label: string, display: bool, required: bool}>
     */
    public function exposable(FormRuntime $rt): array
    {
        $out = [];
        foreach ($rt->mainFields() as $f) {
            if (! $this->canExpose($rt, $f)) {
                continue;
            }
            $type = $rt->type($f);
            $out[] = [
                'uuid' => $f['uuid'], 'key' => $f['key'], 'type' => $f['type'],
                'label' => $this->translator->labelOf($f['i18n']['label'] ?? null, Translator::humanize((string) $f['key'])),
                'display' => $type !== null && ! $type->isStored(),
                'required' => (bool) ($f['validation']['required'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Validates an external form's settings and returns the row, the
     * translations and the password change.
     *
     * @param  array<string, mixed>  $p
     * @return array{row: array<string, mixed>, i18n: array<string, array<string, string>>, password: string|false|null}
     */
    public function normalize(array $p, ?ExternalForm $existing): array
    {
        $errors = [];
        $form = is_string($p['form'] ?? null) && Str::isUuid($p['form']) ? Form::query()->where('uuid', strtolower($p['form']))->first() : null;
        $rt = $form === null || ! in_array($form->state, ['published', 'schema_inconsistent'], true) ? null : $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['form' => __('external.form_unpublished')]);
        }

        $slug = is_string($p['slug'] ?? null) ? strtolower(trim($p['slug'])) : '';
        if (preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/', $slug) !== 1) {
            $errors['slug'] = __('external.slug_invalid');
        } elseif (ExternalForm::query()->withoutGlobalScope('organization')->where('slug', $slug)->when($existing !== null, fn ($q) => $q->where('id', '!=', $existing?->id))->exists()) {
            $errors['slug'] = __('external.slug_taken');
        }

        $i18n = ['title' => self::texts($p['title'] ?? [], 255), 'intro' => self::texts($p['intro'] ?? [], self::MAX_TEXT), 'success_message' => self::texts($p['success_message'] ?? [], self::MAX_TEXT)];
        if ($i18n['title'] === []) {
            $errors['title'] = __('external.title_required');
        }

        $themeId = null;
        if (($p['theme'] ?? null) !== null) {
            $theme = is_string($p['theme']) && Str::isUuid($p['theme']) ? Theme::query()->where('uuid', strtolower($p['theme']))->first() : null;
            $theme === null ? $errors['theme'] = __('external.theme_unknown') : $themeId = $theme->id;
        }

        $statusId = null;
        if (($p['initial_status'] ?? null) !== null) {
            $statusId = is_string($p['initial_status']) && Str::isUuid($p['initial_status'])
                ? DB::table('statuses')->where('form_id', $form->id)->where('uuid', strtolower($p['initial_status']))->whereNull('archived_at')->value('id') : null;
            if ($statusId === null) {
                $errors['initial_status'] = __('external.status_unknown');
            }
        }

        // Shown fields: an allow-list of exposable fields, at least one of them stored.
        $exposable = [];
        foreach ($this->exposable($rt) as $e) {
            $exposable[$e['uuid']] = $e;
        }
        $exposed = array_values(array_unique(array_map(static fn ($u) => strtolower((string) $u), array_filter((array) ($p['fields'] ?? []), 'is_string'))));
        $unknown = array_diff($exposed, array_keys($exposable));
        if ($unknown !== []) {
            $errors['fields'] = __('external.field_not_exposable');
        } elseif (array_filter($exposed, static fn (string $u) => ! $exposable[$u]['display']) === []) {
            $errors['fields'] = __('external.fields_required');
        } else {
            $missing = [];
            foreach ($rt->mainFields() as $f) {
                $type = $rt->type($f);
                if ($type === null || ! $type->isStored() || $type->calculated || $type->storage === 'auto_number' || in_array($f['uuid'], $exposed, true)) {
                    continue;
                }
                if (($f['validation']['required'] ?? false) && ($f['behavior']['default'] ?? null) === null) {
                    $missing[] = $this->translator->labelOf($f['i18n']['label'] ?? null, Translator::humanize((string) $f['key']));
                }
            }
            if ($missing !== []) {
                $errors['fields'] = __('external.required_not_shown', ['fields' => implode('، ', $missing)]);
            }
        }

        // Protections.
        $rate = filter_var($p['rate_limit_per_ip'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($rate === false) {
            $errors['rate_limit_per_ip'] = __('external.rate_invalid');
        }
        $cap = ($p['submission_cap'] ?? null) === null ? null : filter_var($p['submission_cap'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100_000_000]]);
        if ($cap === false) {
            $errors['submission_cap'] = __('external.cap_invalid');
        }
        $opens = self::moment($p['opens_at'] ?? null);
        $closes = self::moment($p['closes_at'] ?? null);
        if ($opens === false) {
            $errors['opens_at'] = __('external.time_invalid');
        }
        if ($closes === false) {
            $errors['closes_at'] = __('external.time_invalid');
        }
        if ($opens instanceof Carbon && $closes instanceof Carbon && $closes->lessThanOrEqualTo($opens)) {
            $errors['closes_at'] = __('external.closes_before_opens');
        }
        $window = null;
        if (($p['daily_window'] ?? null) !== null) {
            $w = (array) $p['daily_window'];
            $days = array_values(array_unique(array_map('intval', array_filter((array) ($w['days'] ?? []), static fn ($d) => is_int($d) && $d >= 0 && $d <= 6))));
            sort($days);
            $from = is_string($w['from'] ?? null) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $w['from']) === 1 ? $w['from'] : null;
            $to = is_string($w['to'] ?? null) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $w['to']) === 1 ? $w['to'] : null;
            if ($from === null || $to === null || $from === $to || $days === []) {
                $errors['daily_window'] = __('external.window_invalid');
            } else {
                $window = ['from' => $from, 'to' => $to, 'days' => $days];
            }
        }
        $verification = in_array($p['verification'] ?? 'none', ['none', 'email', 'sms'], true) ? (string) ($p['verification'] ?? 'none') : null;
        if ($verification === null) {
            $errors['verification'] = __('external.verification_invalid');
        } elseif ($verification === 'sms' && ! $this->codes->smsAvailable()) {
            $errors['verification'] = __('external.sms_unavailable');
        }

        // Who may open it.
        $audience = in_array($p['audience'] ?? 'public', ['public', 'accounts'], true) ? (string) ($p['audience'] ?? 'public') : 'public';
        $roleIds = [];
        foreach (array_filter((array) ($p['roles'] ?? []), 'is_string') as $uuid) {
            $id = Str::isUuid($uuid) ? DB::table('roles')->where('organization_id', $this->tenant->organizationId())->where('uuid', strtolower($uuid))->where('audience', 'external')->value('id') : null;
            $id === null ? $errors['roles'] = __('external.role_unknown') : $roleIds[] = (int) $id;
        }
        sort($roleIds);

        // The run-as user creates the records and must be able to.
        $runAs = is_string($p['run_as'] ?? null) && Str::isUuid($p['run_as']) ? User::query()->where('uuid', strtolower($p['run_as']))->where('status', 'active')->first() : null;
        if ($runAs === null) {
            $errors['run_as'] = __('external.run_as_unknown');
        } elseif (! ($this->fieldAccess->resolve($runAs, $form->id, $form->uuid, $rt->definition, 'create')['modes']['create'] ?? false)) {
            $errors['run_as'] = __('external.run_as_cannot_create');
        }

        // The password: a new one, kept, or removed.
        $password = null;
        if (($p['clear_password'] ?? false) === true) {
            $password = false;
        } elseif (is_string($p['password'] ?? null) && $p['password'] !== '') {
            if (mb_strlen($p['password']) < 8 || mb_strlen($p['password']) > 128) {
                $errors['password'] = __('external.password_length');
            } else {
                $password = $p['password'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'row' => [
                'form_id' => $form->id, 'slug' => $slug, 'theme_id' => $themeId, 'initial_status_id' => $statusId === null ? null : (int) $statusId,
                'exposed_fields' => $exposed, 'captcha' => (bool) ($p['captcha'] ?? false), 'rate_limit_per_ip' => (int) $rate,
                'opens_at' => $opens instanceof Carbon ? $opens : null, 'closes_at' => $closes instanceof Carbon ? $closes : null, 'daily_window' => $window,
                'submission_cap' => $cap === null ? null : (int) $cap, 'verification' => $verification, 'run_as_user_id' => $runAs?->id,
                'is_active' => (bool) ($p['active'] ?? true), 'audience' => $audience, 'role_ids' => $audience === 'accounts' && $roleIds !== [] ? $roleIds : null,
            ],
            'i18n' => $i18n,
            'password' => $password,
        ];
    }

    /** @return array<string, mixed> */
    public function present(ExternalForm $x): array
    {
        $doc = [
            'uuid' => strtolower($x->uuid),
            'form' => strtolower((string) DB::table('forms')->where('id', $x->form_id)->value('uuid')),
            'slug' => $x->slug,
            'title' => (object) $x->translationsFor('title'), 'intro' => (object) $x->translationsFor('intro'), 'success_message' => (object) $x->translationsFor('success_message'),
            'theme' => $x->theme_id === null ? null : strtolower((string) DB::table('themes')->where('id', $x->theme_id)->value('uuid')),
            'initial_status' => $x->initial_status_id === null ? null : strtolower((string) DB::table('statuses')->where('id', $x->initial_status_id)->value('uuid')),
            'fields' => array_values($x->exposed_fields),
            'captcha' => $x->captcha, 'rate_limit_per_ip' => $x->rate_limit_per_ip,
            'opens_at' => $x->opens_at?->utc()->toIso8601ZuluString(), 'closes_at' => $x->closes_at?->utc()->toIso8601ZuluString(),
            'daily_window' => $x->daily_window, 'submission_cap' => $x->submission_cap, 'verification' => $x->verification,
            'run_as' => strtolower((string) DB::table('users')->where('id', $x->run_as_user_id)->value('uuid')),
            'active' => $x->is_active, 'audience' => $x->audience,
            'roles' => DB::table('roles')->whereIn('id', $x->role_ids ?? [])->orderBy('id')->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->values()->all(),
            'has_password' => $x->password_hash !== null,
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));

        return $doc + $this->summary($x) + ['run_as_name' => DB::table('users')->where('id', $x->run_as_user_id)->value('name')];
    }

    /** @return array<string, mixed> */
    public function summary(ExternalForm $x): array
    {
        $form = DB::table('forms')->where('id', $x->form_id)->first(['id', 'key']);

        return [
            'uuid' => strtolower($x->uuid), 'slug' => $x->slug, 'url' => url('/x/f/'.$x->slug),
            'label' => $this->translator->labelOf($x->translationsFor('title'), Translator::humanize($x->slug)),
            'form_name' => $form === null ? null : ($this->translator->many('form', [(int) $form->id], ['name'])[(int) $form->id]['name'] ?? Translator::humanize((string) $form->key)),
            'active' => $x->is_active, 'audience' => $x->audience, 'submissions' => $x->submissions_count, 'cap' => $x->submission_cap,
            'availability' => $this->availability($x),
        ];
    }

    /** The published external form at a slug, switching to its organization. */
    public function find(string $slug): ?ExternalForm
    {
        /** @var ExternalForm|null $x */
        $x = ExternalForm::query()->withoutGlobalScope('organization')->where('slug', strtolower($slug))->first();
        if ($x !== null) {
            $this->tenant->set($x->organization_id);
        }

        return $x;
    }

    /** Why the form cannot be filled in now, or null when it can. */
    public function availability(ExternalForm $x): ?string
    {
        if (! $x->is_active) {
            return 'inactive';
        }
        $now = Carbon::now('UTC');
        if ($x->opens_at !== null && $now->lessThan($x->opens_at)) {
            return 'not_open';
        }
        if ($x->closes_at !== null && $now->greaterThanOrEqualTo($x->closes_at)) {
            return 'closed';
        }
        if ($x->submission_cap !== null && $x->submissions_count >= $x->submission_cap) {
            return 'full';
        }
        if ($x->daily_window !== null) {
            $local = $now->copy()->setTimezone((string) $this->settings->get('formats', 'timezone'));
            $time = $local->format('H:i');
            $from = $x->daily_window['from'];
            $to = $x->daily_window['to'];
            // A window that ends before it starts runs past midnight; the day is the one it started on.
            $inside = $from < $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);
            $day = $from > $to && $time < $to ? $local->copy()->subDay()->dayOfWeek : $local->dayOfWeek;
            if (! $inside || ! in_array($day, $x->daily_window['days'] ?? [], true)) {
                return 'outside_hours';
            }
        }

        return null;
    }

    public function runtime(ExternalForm $x): ?FormRuntime
    {
        $form = Form::query()->find($x->form_id);

        return $form === null || ! in_array($form->state, ['published', 'schema_inconsistent'], true) ? null : $this->runtimes->forForm($form);
    }

    public function runAs(ExternalForm $x): ?User
    {
        return User::query()->whereKey($x->run_as_user_id)->where('status', 'active')->first();
    }

    /** Whether the visitor may open the form: anyone, or a signed-in external user with one of its roles. */
    public function admits(ExternalForm $x, ?ExternalUser $visitor): bool
    {
        if ($x->audience === 'public') {
            return true;
        }

        return $visitor !== null && $visitor->status === 'approved' && ($x->role_ids === null || in_array($visitor->role_id, $x->role_ids, true));
    }

    public function passwordMatches(ExternalForm $x, mixed $password): bool
    {
        return $x->password_hash === null || (is_string($password) && Hash::check($password, $x->password_hash));
    }

    /**
     * What the page shows: texts, theme, protections and the definition
     * holding only the shown fields.
     *
     * @return array<string, mixed>
     */
    public function page(ExternalForm $x, FormRuntime $rt, User $runAs, bool $withDefinition): array
    {
        $theme = $x->theme_id === null ? null : Theme::query()->find($x->theme_id);
        $out = [
            'slug' => $x->slug,
            'title' => $this->translator->labelOf($x->translationsFor('title'), Translator::humanize($x->slug)),
            'intro' => $this->translator->pick($x->translationsFor('intro')),
            'theme' => $theme === null ? null : app(Themes::class)->client($theme),
            'captcha' => $x->captcha, 'verification' => $x->verification, 'password' => $x->password_hash !== null,
            'audience' => $x->audience, 'availability' => $this->availability($x),
        ];
        if ($withDefinition) {
            $out['definition'] = $this->definition($x, $rt, $runAs);
        }

        return $out;
    }

    /**
     * The client definition of the shown fields: every other field and every
     * group left empty is hidden, and the expression context names no one.
     *
     * @return array<string, mixed>
     */
    public function definition(ExternalForm $x, FormRuntime $rt, User $runAs): array
    {
        $levels = $this->fieldAccess->resolve($runAs, $rt->form->id, $rt->form->uuid, $rt->definition, 'create');
        $shown = array_flip($x->exposed_fields);
        foreach ($rt->definition['fields'] as $f) {
            if (! isset($shown[$f['uuid']]) || ! $this->canExpose($rt, $f)) {
                $levels['fields'][$f['uuid']] = 'hidden';
            } elseif (! isset($levels['fields'][$f['uuid']])) {
                $levels['fields'][$f['uuid']] = 'editable';
            }
        }
        $levels = self::hideEmptyGroups($rt, $levels);
        $definition = $this->client->build($rt->definition, $levels, 'create');
        $definition['relations'] = [];
        $definition['form']['titleTemplate'] = null;

        return $definition + ['user' => AccessLinks::nobody()];
    }

    /**
     * Hides every group with no visible field inside it, so a page shows
     * only what it is about.
     *
     * @param  array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}  $levels
     * @return array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}
     */
    public static function hideEmptyGroups(FormRuntime $rt, array $levels): array
    {
        $visible = [];
        foreach ($rt->definition['fields'] as $f) {
            if (($levels['fields'][$f['uuid']] ?? 'hidden') !== 'hidden') {
                for ($g = $f['group'] ?? null, $seen = []; $g !== null && ! isset($seen[$g]); $g = $rt->groups[$g]['parent'] ?? null) {
                    $seen[$g] = true;
                    $visible[$g] = true;
                }
            }
        }
        foreach ($rt->definition['groups'] as $g) {
            if (! isset($visible[$g['uuid']])) {
                $levels['groups'][$g['uuid']] = 'hidden';
            }
        }

        return $levels;
    }

    /**
     * Takes a submission. Returns the reference shown to the visitor.
     *
     * @param  array<string, mixed>  $input  values by field key
     * @param  array<string, mixed>  $proof  password, captcha, verification
     * @return array{reference: string|null, message: string|null}
     */
    public function submit(ExternalForm $x, FormRuntime $rt, User $runAs, ?ExternalUser $visitor, array $input, array $proof, string $submission, string $ip): array
    {
        if (! $this->passwordMatches($x, $proof['password'] ?? null)) {
            throw new RecordException(403, 'password', __('external.password_wrong'));
        }
        if ($x->captcha && ! $this->pow->verify($proof['captcha'] ?? null)) {
            throw new RecordException(422, 'captcha', __('external.captcha_failed'));
        }
        $verified = null;
        if ($x->verification !== 'none') {
            $verified = $this->codes->normalize($x->verification, $proof['verification']['to'] ?? null);
            if ($verified === null || ! $this->codes->check('form:'.$x->id, $verified, $proof['verification']['code'] ?? null)) {
                throw new RecordException(422, 'verification', __('external.code_wrong'));
            }
        }
        if (! $this->throttles->take($x, 'ip', $ip, $x->rate_limit_per_ip)) {
            throw new RecordException(429, 'throttled', __('external.too_many'));
        }
        if ($verified !== null && ! $this->throttles->take($x, 'email', $verified, $x->rate_limit_per_ip)) {
            throw new RecordException(429, 'throttled', __('external.too_many'));
        }

        // Reserve a place under the cap first; give it back when the record is not created.
        $reserved = DB::table('external_forms')->where('id', $x->id)
            ->where(static fn ($q) => $q->whereNull('submission_cap')->orWhereColumn('submissions_count', '<', 'submission_cap'))
            ->update(['submissions_count' => DB::raw('submissions_count + 1')]);
        if ($reserved === 0) {
            throw new RecordException(409, 'full', __('external.unavailable.full'));
        }

        $shownKeys = [];
        foreach ($rt->definition['fields'] as $f) {
            if (in_array($f['uuid'], $x->exposed_fields, true) && $this->canExpose($rt, $f)) {
                $shownKeys[$f['key']] = true;
            }
        }
        $values = array_intersect_key($input, $shownKeys);
        $previous = Auth::user();
        Auth::setUser($runAs);
        try {
            $done = $this->actor->as($visitor?->id, fn (): array => $this->pipeline->create(
                $rt, $runAs, $values, [], 'x-'.$x->id.'-'.$submission, 'external',
                $visitor === null ? [] : ['external_user_id' => $visitor->id], $x->initial_status_id,
            ));
        } catch (RecordException $e) {
            DB::table('external_forms')->where('id', $x->id)->where('submissions_count', '>', 0)->update(['submissions_count' => DB::raw('submissions_count - 1')]);
            throw $this->outward($e, $shownKeys);
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
        $this->actor->as($visitor?->id, fn () => $this->audit->record(
            'external.form_submitted', 'data', null, 'external_form', $x->id,
            ['slug' => $x->slug, 'verified' => $verified, 'record' => $done['uuid']], $runAs->id, null, $rt->form->id, $done['id'],
        ));
        $number = DB::table($rt->table)->where('id', $done['id'])->value('record_number');

        return [
            'reference' => $number === null ? null : (string) $number,
            'message' => $this->translator->pick($x->translationsFor('success_message')),
        ];
    }

    /**
     * A record error as the visitor may see it: messages about fields the
     * form shows, and one general message for everything else.
     *
     * @param  array<string, true>  $shownKeys
     */
    private function outward(RecordException $e, array $shownKeys): RecordException
    {
        if ($e->reason === 'conflict' || $e->reason === 'duplicate') {
            return new RecordException(409, $e->reason, __('external.duplicate'));
        }
        $errors = (array) ($e->payload['errors'] ?? []);
        $shown = [];
        $other = false;
        foreach ($errors as $path => $messages) {
            $key = explode('.', (string) $path)[0];
            isset($shownKeys[$key]) ? $shown[(string) $path] = $messages : $other = true;
        }
        if ($other || ($errors === [] && $e->status !== 422)) {
            $shown['_form'] = [__('external.cannot_submit')];
        }

        return new RecordException(422, 'invalid', __('records.invalid'), ['errors' => $shown]);
    }

    /** @param  array<string, mixed>  $f */
    private function canExpose(FormRuntime $rt, array $f): bool
    {
        if (isset($rt->fieldRepeater[$f['uuid']]) || $this->insideDataGroup($rt, $f['group'] ?? null)) {
            return false;
        }
        $type = $rt->type($f);
        if ($type === null || in_array($type->key, self::HIDDEN_TYPES, true) || in_array($type->category, self::HIDDEN_CATEGORIES, true) || ($f['hook']['hook'] ?? null) === 'component') {
            return false;
        }

        return ! $type->options || ($f['options']['source'] ?? 'static') === 'static';
    }

    private function insideDataGroup(FormRuntime $rt, ?string $group): bool
    {
        for ($seen = []; $group !== null && isset($rt->groups[$group]) && ! isset($seen[$group]); $group = $rt->groups[$group]['parent'] ?? null) {
            $seen[$group] = true;
            if (in_array($rt->groups[$group]['type'] ?? null, FieldTypeRegistry::DATA_GROUPS, true)) {
                return true;
            }
        }

        return false;
    }

    private static function moment(mixed $v): Carbon|false|null
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_string($v)) {
            return false;
        }
        try {
            return Carbon::parse($v)->utc();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, string> */
    private static function texts(mixed $values, int $max): array
    {
        $out = [];
        foreach ((array) $values as $locale => $v) {
            if (is_string($locale) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $locale) === 1 && is_string($v) && trim($v) !== '') {
                $out[$locale] = mb_substr(trim($v), 0, $max);
            }
        }

        return $out;
    }
}
