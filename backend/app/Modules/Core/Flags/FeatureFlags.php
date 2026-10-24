<?php

declare(strict_types=1);

namespace App\Modules\Core\Flags;

use App\Modules\Access\AccessCache;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use App\Support\Json\Canonical;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Feature flags (specification §4.36, ADR-0062).
 *
 * A flag puts one form, action, page or capability behind an audience of
 * roles, departments (with the departments under them) and people. While
 * the flag is on, only its audience sees the target, or everyone when the
 * audience is empty; while it is off, no one does. A target without a flag
 * is untouched. Flags are enforced on the server: a flagged form, action or
 * page answers 404 to those outside, a capability 403.
 */
final class FeatureFlags
{
    /** Capabilities that can be released gradually, with the requests they cover under /api/v1. */
    public const CAPABILITIES = [
        'global_search' => [['GET', 'api/v1/search']],
        'insights' => [['GET', 'api/v1/insights']],
        'personal_api_tokens' => [['*', 'api/v1/me/tokens*']],
        'record_import' => [['*', 'api/v1/r/*/imports*']],
        'record_export' => [['POST', 'api/v1/r/*/exports'], ['POST', 'api/v1/r/*/downloads/*']],
        'record_sharing' => [['*', 'api/v1/r/*/*/links*'], ['*', 'api/v1/r/*/*/signature-requests*']],
    ];

    public const TYPES = ['form', 'action', 'page', 'capability'];

    /** @var array<int, list<array{type: string, target: int|null, key: string, on: bool}>>|null */
    private ?array $offMemo = null;

    public function __construct(
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly AccessCache $cache,
    ) {}

    /** @return array<string, mixed> */
    public function present(FeatureFlag $f): array
    {
        $doc = [
            'uuid' => strtolower($f->uuid), 'key' => $f->key, 'type' => $f->target_type, 'enabled' => $f->is_enabled,
            'application' => $f->application_id === null ? null : strtolower((string) Application::query()->whereKey($f->application_id)->value('uuid')),
            'target' => $this->targetUuid($f->target_type, $f->target_id),
            'audience' => ['roles' => array_values($f->audience['roles'] ?? []), 'departments' => array_values($f->audience['departments'] ?? []), 'users' => array_values($f->audience['users'] ?? [])],
            'i18n' => ['description' => (object) $f->translationsFor('description')],
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['label'] = $this->targetLabel($f) ?? Translator::humanize($f->key);

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?FeatureFlag $existing): array
    {
        $errors = [];
        $type = (string) ($d['type'] ?? '');
        if (! in_array($type, self::TYPES, true)) {
            $errors['type'][] = __('platform.flag_bad_type');
        }
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) !== 1) {
            $errors['key'][] = __('platform.flag_bad_key');
        } elseif (FeatureFlag::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('platform.flag_key_taken');
        }
        $application = null;
        if (($d['application'] ?? null) !== null) {
            $application = Str::isUuid((string) $d['application']) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null;
            if ($application === null) {
                $errors['application'][] = __('experience.unknown_application');
            }
        }
        $target = null;
        if ($type === 'capability') {
            $target = (string) ($d['target'] ?? '');
            if (! isset(self::CAPABILITIES[$target])) {
                $errors['target'][] = __('platform.flag_bad_capability');
            }
        } elseif ($type !== '') {
            $row = Str::isUuid((string) ($d['target'] ?? '')) ? $this->targetRow($type, strtolower((string) $d['target'])) : null;
            if ($row === null) {
                $errors['target'][] = __('platform.flag_bad_target');
            } else {
                $target = (int) $row->id;
                if ($application !== null && $row->application_id !== null && (int) $row->application_id !== (int) $application->id) {
                    $errors['target'][] = __('platform.flag_other_application');
                }
            }
        }
        if ($type !== '' && $target !== null && $target !== '' && FeatureFlag::query()->where('target_type', $type)
            ->where($type === 'capability' ? 'key' : 'target_id', $type === 'capability' ? $key : $target)
            ->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['target'][] = __('platform.flag_target_taken');
        }
        if ($type === 'capability' && $target !== null && $key !== $target) {
            // A capability's flag is known by the capability itself.
            $key = (string) $target;
        }
        $audience = [];
        foreach (['roles' => 'roles', 'departments' => 'departments', 'users' => 'users'] as $part => $table) {
            $audience[$part] = [];
            foreach (array_slice((array) ($d['audience'][$part] ?? []), 0, 500) as $u) {
                if (is_string($u) && Str::isUuid($u) && DB::table($table)->where('uuid', strtolower($u))->exists()) {
                    $audience[$part][] = strtolower($u);
                } else {
                    $errors["audience.{$part}"][] = __('platform.flag_unknown_audience');
                }
            }
            $audience[$part] = array_values(array_unique($audience[$part]));
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'key' => $key, 'target_type' => $type, 'target_id' => $type === 'capability' ? null : $target, 'application' => $application,
            'enabled' => (bool) ($d['enabled'] ?? true), 'audience' => $audience, 'description' => $this->i18n($d['i18n']['description'] ?? []),
        ];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?FeatureFlag $existing, array $p, User $actor): FeatureFlag
    {
        return DB::transaction(function () use ($existing, $p, $actor): FeatureFlag {
            $f = $existing ?? new FeatureFlag;
            if ($existing === null) {
                $f->uuid = (string) Str::uuid7();
            }
            $f->forceFill([
                'key' => $p['key'], 'target_type' => $p['target_type'], 'target_id' => $p['target_id'], 'application_id' => $p['application']?->id,
                'is_enabled' => $p['enabled'], 'audience' => $p['audience'],
            ])->save();
            $f->setTranslations('description', $p['description']);
            $this->audit->record($existing === null ? 'feature_flag.created' : 'feature_flag.updated', 'config', null, 'feature_flag', $f->id,
                ['key' => $f->key, 'enabled' => $f->is_enabled], $actor->id);
            $this->cache->bump();
            $this->offMemo = null;

            return $f->refresh();
        });
    }

    public function delete(FeatureFlag $f, User $actor): void
    {
        $this->audit->record('feature_flag.deleted', 'config', null, 'feature_flag', $f->id, ['key' => $f->key], $actor->id);
        $f->delete();
        $this->cache->bump();
        $this->offMemo = null;
    }

    /** Whether the person may see the target: true when no flag covers it. */
    public function allows(User $user, string $type, int|string $target): bool
    {
        foreach ($this->flagsFor($user) as $f) {
            if ($f['type'] === $type && ($type === 'capability' ? $f['key'] === $target : $f['target'] === (int) $target)) {
                return $f['on'];
            }
        }

        return true;
    }

    /** Capabilities that are off for the person, so screens hide them. @return list<string> */
    public function capabilitiesOff(User $user): array
    {
        return array_values(array_map(static fn ($f) => $f['key'], array_filter($this->flagsFor($user), static fn ($f) => $f['type'] === 'capability' && ! $f['on'])));
    }

    /** Ids of targets of one kind that are off for the person. @return list<int> */
    public function hiddenIds(User $user, string $type): array
    {
        return array_values(array_map(static fn ($f) => (int) $f['target'], array_filter($this->flagsFor($user), static fn ($f) => $f['type'] === $type && ! $f['on'])));
    }

    /** Which capability, form, action or page of this request the person may not use: [type, status] or null. */
    public function refusal(User $user, Request $request): ?array
    {
        $path = ltrim($request->path(), '/');
        $method = $request->method();
        foreach (self::CAPABILITIES as $capability => $rules) {
            foreach ($rules as [$m, $pattern]) {
                if (($m === '*' || $m === $method || ($m === 'GET' && $method === 'HEAD')) && Str::is($pattern, $path) && ! $this->allows($user, 'capability', $capability)) {
                    return ['capability', 403];
                }
            }
        }
        $route = $request->route();
        $param = static fn (string $name): ?string => is_object($route) && is_string($p = $route->parameter($name)) && Str::isUuid($p) ? strtolower($p) : (is_object($route) && is_object($p = $route->parameter($name)) && isset($p->uuid) ? strtolower((string) $p->uuid) : null);
        if (Str::is('api/v1/r/*', $path) && ($form = $param('form')) !== null && ($id = DB::table('forms')->where('uuid', $form)->value('id')) !== null && ! $this->allows($user, 'form', (int) $id)) {
            return ['form', 404];
        }
        if (Str::is('api/v1/r/*/actions/*', $path) && ($action = $param('action')) !== null && ($id = DB::table('actions')->where('uuid', $action)->value('id')) !== null && ! $this->allows($user, 'action', (int) $id)) {
            return ['action', 404];
        }
        if (Str::is('api/v1/pages/*/view', $path) && ($page = $param('page')) !== null && ($id = DB::table('pages')->where('uuid', $page)->value('id')) !== null && ! $this->allows($user, 'page', (int) $id)) {
            return ['page', 404];
        }

        return null;
    }

    /** @return list<array{type: string, target: int|null, key: string, on: bool}> */
    private function flagsFor(User $user): array
    {
        if (isset($this->offMemo[$user->id])) {
            return $this->offMemo[$user->id];
        }
        $flags = FeatureFlag::query()->get();
        if ($flags->isEmpty()) {
            return $this->offMemo[$user->id] = [];
        }
        $roles = $user->activeRoles()->pluck('roles.uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        $departments = $user->department_id === null ? [] : DB::table('department_closure')->join('departments', 'departments.id', '=', 'department_closure.ancestor_id')
            ->where('department_closure.descendant_id', $user->department_id)->pluck('departments.uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        $me = strtolower((string) $user->uuid);

        return $this->offMemo[$user->id] = $flags->map(static function (FeatureFlag $f) use ($roles, $departments, $me): array {
            $a = $f->audience;
            $empty = ($a['roles'] ?? []) === [] && ($a['departments'] ?? []) === [] && ($a['users'] ?? []) === [];
            $in = $empty || array_intersect($a['roles'] ?? [], $roles) !== [] || array_intersect($a['departments'] ?? [], $departments) !== [] || in_array($me, $a['users'] ?? [], true);

            return ['type' => $f->target_type, 'target' => $f->target_id, 'key' => $f->key, 'on' => $f->is_enabled && $in];
        })->values()->all();
    }

    private function targetRow(string $type, string $uuid): ?object
    {
        return match ($type) {
            'form' => DB::table('forms')->where('uuid', $uuid)->first(['id', 'application_id']),
            'action' => DB::table('actions')->join('forms', 'forms.id', '=', 'actions.form_id')->where('actions.uuid', $uuid)->first(['actions.id', 'forms.application_id']),
            'page' => DB::table('pages')->where('uuid', $uuid)->first(['id', 'application_id']),
            default => null,
        };
    }

    private function targetUuid(string $type, ?int $id): ?string
    {
        $table = ['form' => 'forms', 'action' => 'actions', 'page' => 'pages'][$type] ?? null;

        return $table === null || $id === null ? null : strtolower((string) DB::table($table)->where('id', $id)->value('uuid'));
    }

    private function targetLabel(FeatureFlag $f): ?string
    {
        if ($f->target_type === 'capability') {
            return __("platform.capability.{$f->key}");
        }
        // Each kind keeps its display text under its own name.
        $field = ['form' => 'name', 'action' => 'label', 'page' => 'name'][$f->target_type] ?? null;
        if ($field === null || $f->target_id === null) {
            return null;
        }
        $names = $this->translator->allMany($f->target_type, [$f->target_id]);

        return $this->translator->pick((array) ($names[$f->target_id][$field] ?? []));
    }

    /**
     * @return array<string, string>
     */
    private function i18n(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $locale => $text) {
            if (is_string($locale) && is_string($text) && trim($text) !== '') {
                $out[$locale] = mb_substr(trim($text), 0, 500);
            }
        }

        return $out;
    }
}
