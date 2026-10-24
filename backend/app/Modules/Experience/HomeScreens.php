<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Models\HomeScreen;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Reports\Models\Report;
use App\Support\Html\HtmlSanitizer;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Home screens (specification §4.29, ADR-0054): the landing page for
 * everyone, a role, a department or one person — the main one, or an
 * application's — assembled from widgets on a twelve-column grid.
 *
 * Which screen someone gets follows the access precedence: their own, then
 * one of their roles', then their department's (or a parent department's),
 * then the default; within a tier the highest priority wins. Widgets only
 * ever show what the person may already open: each one is filtered by its
 * rule on the person and by the permission of the report or form it reads,
 * and the data itself is loaded through the ordinary, permission-checked
 * endpoints.
 */
final class HomeScreens
{
    public const TARGETS = ['default', 'role', 'department', 'user'];

    /** Widgets: a report covers charts and key figures (its visualization decides). */
    public const WIDGETS = ['my_work', 'my_records', 'report', 'shortcuts', 'announcements', 'recent_activity', 'pinned_links', 'embedded_table', 'html'];

    private const MAX_WIDGETS = 30;

    public function __construct(
        private readonly Translator $translator,
        private readonly OwnedConditions $conditions,
        private readonly PersonRules $rules,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /** @return array<string, mixed> */
    public function present(HomeScreen $h): array
    {
        $widgets = DB::table('page_widgets')->where('home_screen_id', $h->id)->orderBy('sort_order')->orderBy('id')->get();
        $asts = $this->conditions->many($widgets->pluck('visibility_condition_id')->all());
        $doc = [
            'uuid' => strtolower($h->uuid),
            'application' => $h->application_id === null ? null : strtolower((string) Application::query()->whereKey($h->application_id)->value('uuid')),
            'target_type' => $h->target_type,
            'target' => $this->targetUuid($h->target_type, $h->target_id),
            'priority' => $h->priority,
            'active' => $h->is_active,
            'i18n' => ['name' => (object) $h->translationsFor('name')],
            'widgets' => $widgets->map(static function (object $w) use ($asts): array {
                $config = json_decode((string) $w->config, true) ?: [];
                $layout = json_decode((string) $w->layout, true) ?: [];

                return [
                    'uuid' => strtolower((string) $w->uuid), 'type' => $w->type,
                    'i18n' => ['title' => (object) ($config['title'] ?? []), 'text' => (object) ($config['text'] ?? [])],
                    'report' => $config['report'] ?? null, 'form' => $config['form'] ?? null, 'limit' => (int) ($config['limit'] ?? 5),
                    'links' => array_values((array) ($config['links'] ?? [])),
                    'condition' => $w->visibility_condition_id === null ? null : ($asts[(int) $w->visibility_condition_id] ?? null),
                    'layout' => ['w' => (int) ($layout['w'] ?? 6), 'h' => (int) ($layout['h'] ?? 2)],
                ];
            })->values()->all(),
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['label'] = $this->translator->labelOf($h->translationsFor('name'), __('experience.home_screen'));

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?HomeScreen $existing): array
    {
        $errors = [];
        $name = self::i18n($d['i18n']['name'] ?? []);
        if ($name === []) {
            $errors['i18n.name'][] = __('experience.name_required');
        }
        $application = null;
        if (($d['application'] ?? null) !== null) {
            $application = Str::isUuid((string) $d['application']) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null;
            if ($application === null) {
                $errors['application'][] = __('experience.unknown_application');
            }
        }
        $targetType = (string) ($d['target_type'] ?? 'default');
        $targetId = null;
        if (! in_array($targetType, self::TARGETS, true)) {
            $errors['target_type'][] = __('experience.bad_target');
        } elseif ($targetType !== 'default') {
            $table = ['role' => 'roles', 'department' => 'departments', 'user' => 'users'][$targetType];
            $targetId = Str::isUuid((string) ($d['target'] ?? '')) ? DB::table($table)->where('uuid', strtolower((string) $d['target']))->value('id') : null;
            if ($targetId === null) {
                $errors['target'][] = __('experience.choose_target');
            }
        }
        $priority = (int) ($d['priority'] ?? 0);
        if ($priority < 0 || $priority > 1000) {
            $errors['priority'][] = __('validation.between.numeric', ['attribute' => 'priority', 'min' => 0, 'max' => 1000]);
        }
        $widgets = [];
        $list = array_values((array) ($d['widgets'] ?? []));
        if (count($list) > self::MAX_WIDGETS) {
            $errors['widgets'][] = __('reports.too_many', ['max' => self::MAX_WIDGETS]);
        }
        foreach (array_slice($list, 0, self::MAX_WIDGETS) as $i => $w) {
            $p = "widgets.{$i}";
            $type = (string) ($w['type'] ?? '');
            if (! in_array($type, self::WIDGETS, true)) {
                $errors["{$p}.type"][] = __('experience.bad_widget');

                continue;
            }
            $config = ['title' => self::i18n($w['i18n']['title'] ?? []), 'limit' => max(1, min(50, (int) ($w['limit'] ?? 5)))];
            if ($type === 'report') {
                $report = Str::isUuid((string) ($w['report'] ?? '')) ? Report::query()->where('uuid', strtolower((string) $w['report']))->value('uuid') : null;
                $report === null ? $errors["{$p}.report"][] = __('experience.choose_report') : $config['report'] = strtolower((string) $report);
            }
            if ($type === 'my_records' || $type === 'embedded_table') {
                $form = Str::isUuid((string) ($w['form'] ?? '')) ? Form::query()->where('uuid', strtolower((string) $w['form']))->whereIn('kind', ['form', 'collection'])->value('uuid') : null;
                $form === null ? $errors["{$p}.form"][] = __('experience.choose_form') : $config['form'] = strtolower((string) $form);
            }
            if ($type === 'shortcuts' || $type === 'pinned_links') {
                $links = [];
                foreach (array_slice(array_values((array) ($w['links'] ?? [])), 0, 20) as $j => $l) {
                    $label = self::i18n($l['i18n']['label'] ?? []);
                    if ($type === 'shortcuts') {
                        $form = Str::isUuid((string) ($l['form'] ?? '')) ? Form::query()->where('uuid', strtolower((string) $l['form']))->value('uuid') : null;
                        $action = in_array($l['action'] ?? 'list', ['list', 'new'], true) ? (string) ($l['action'] ?? 'list') : 'list';
                        $form === null ? $errors["{$p}.links.{$j}"][] = __('experience.choose_form') : $links[] = ['form' => strtolower((string) $form), 'action' => $action, 'i18n' => ['label' => $label]];
                    } else {
                        $url = trim((string) ($l['url'] ?? ''));
                        if ($label === [] || preg_match('#^(https://[^\s/]+|/)[^\s]*$#', $url) !== 1 || str_starts_with($url, '//')) {
                            $errors["{$p}.links.{$j}"][] = __('experience.bad_link');
                        } else {
                            $links[] = ['url' => mb_substr($url, 0, 2048), 'i18n' => ['label' => $label]];
                        }
                    }
                }
                if ($links === [] && ! isset($errors["{$p}.links.0"])) {
                    $errors["{$p}.links"][] = __('experience.links_required');
                }
                $config['links'] = $links;
            }
            if ($type === 'html') {
                $text = [];
                foreach (self::i18n($w['i18n']['text'] ?? [], 20000) as $locale => $html) {
                    $clean = trim($this->sanitizer->clean($html));
                    if ($clean !== '') {
                        $text[$locale] = $clean;
                    }
                }
                $text === [] ? $errors["{$p}.text"][] = __('experience.content_required') : $config['text'] = $text;
            }
            $condition = is_array($w['condition'] ?? null) ? $w['condition'] : null;
            if ($condition !== null) {
                $this->rules->check($condition, "{$p}.condition");
            }
            $wd = (int) ($w['layout']['w'] ?? 6);
            $ht = (int) ($w['layout']['h'] ?? 2);
            if ($wd < 3 || $wd > 12 || $ht < 1 || $ht > 6) {
                $errors["{$p}.layout"][] = __('reports.bad_size');
            }
            $widgets[] = [
                'uuid' => is_string($w['uuid'] ?? null) && Str::isUuid($w['uuid']) ? strtolower($w['uuid']) : (string) Str::uuid7(),
                'type' => $type, 'config' => $config, 'condition' => $condition, 'layout' => ['w' => max(3, min(12, $wd)), 'h' => max(1, min(6, $ht))],
            ];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'uuid' => $existing->uuid ?? (string) Str::uuid7(), 'name' => $name, 'application' => $application,
            'target_type' => $targetType, 'target_id' => $targetId === null ? null : (int) $targetId, 'priority' => $priority,
            'active' => (bool) ($d['active'] ?? true), 'widgets' => $widgets,
        ];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?HomeScreen $existing, array $p, User $actor): HomeScreen
    {
        return DB::transaction(function () use ($existing, $p, $actor): HomeScreen {
            $h = $existing ?? new HomeScreen;
            if ($existing === null) {
                $h->uuid = $p['uuid'];
            }
            $h->forceFill([
                'application_id' => $p['application']?->id, 'target_type' => $p['target_type'], 'target_id' => $p['target_id'],
                'priority' => $p['priority'], 'layout' => ['columns' => 12], 'is_active' => $p['active'],
            ])->save();
            $h->setTranslations('name', $p['name']);
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            foreach ($p['widgets'] as $i => $w) {
                $row = ['updated_at' => $now, 'type' => $w['type'], 'sort_order' => $i, 'config' => json_encode($w['config'], JSON_UNESCAPED_UNICODE), 'layout' => json_encode($w['layout'])];
                $current = DB::table('page_widgets')->where('home_screen_id', $h->id)->where('uuid', $w['uuid'])->first(['id', 'visibility_condition_id']);
                if ($current === null) {
                    $id = (int) DB::table('page_widgets')->insertGetId($row + ['uuid' => $w['uuid'], 'created_at' => $now, 'home_screen_id' => $h->id, 'page_id' => null]);
                    $existingCondition = null;
                } else {
                    $id = (int) $current->id;
                    DB::table('page_widgets')->where('id', $id)->update($row);
                    $existingCondition = $current->visibility_condition_id === null ? null : (int) $current->visibility_condition_id;
                }
                $condition = $this->conditions->put(null, 'page_widget', $id, $w['condition'], $existingCondition);
                DB::table('page_widgets')->where('id', $id)->update(['visibility_condition_id' => $condition]);
                $kept[] = $id;
            }
            foreach (DB::table('page_widgets')->where('home_screen_id', $h->id)->whereNotIn('id', $kept === [] ? [0] : $kept)->get(['id', 'visibility_condition_id']) as $gone) {
                DB::table('page_widgets')->where('id', $gone->id)->delete();
                $this->conditions->forget($gone->visibility_condition_id === null ? null : (int) $gone->visibility_condition_id);
            }
            $this->audit->record($existing === null ? 'home_screen.created' : 'home_screen.updated', 'config', null, 'home_screen', $h->id,
                ['target_type' => $h->target_type, 'widgets' => count($p['widgets'])], $actor->id);

            return $h->refresh();
        });
    }

    public function delete(HomeScreen $h, User $actor): void
    {
        DB::transaction(function () use ($h, $actor): void {
            foreach (DB::table('page_widgets')->where('home_screen_id', $h->id)->get(['id', 'visibility_condition_id']) as $w) {
                $this->conditions->forget($w->visibility_condition_id === null ? null : (int) $w->visibility_condition_id);
            }
            DB::table('page_widgets')->where('home_screen_id', $h->id)->delete();
            DB::table('applications')->where('home_screen_id', $h->id)->update(['home_screen_id' => null]);
            $this->audit->record('home_screen.deleted', 'config', null, 'home_screen', $h->id, ['target_type' => $h->target_type], $actor->id);
            $h->delete();
        });
    }

    /** The screen this person lands on: their own, a role's, a department's, then the default. */
    public function resolve(User $user, ?Application $app): ?HomeScreen
    {
        $candidates = HomeScreen::query()->where('is_active', true)
            ->when($app === null, static fn ($q) => $q->whereNull('application_id'), static fn ($q) => $q->where('application_id', $app?->id))
            ->orderByDesc('priority')->orderBy('id')->get();
        if ($candidates->isEmpty()) {
            return null;
        }
        $roles = $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all();
        $departments = $user->department_id === null ? [] : DB::table('department_closure')->where('descendant_id', $user->department_id)
            ->orderBy('depth')->pluck('ancestor_id')->map(static fn ($v) => (int) $v)->all();
        $tiers = [
            static fn (HomeScreen $h): bool => $h->target_type === 'user' && $h->target_id === (int) $user->id,
            static fn (HomeScreen $h): bool => $h->target_type === 'role' && in_array($h->target_id, $roles, true),
            static fn (HomeScreen $h): bool => $h->target_type === 'department' && in_array($h->target_id, $departments, true),
            static fn (HomeScreen $h): bool => $h->target_type === 'default',
        ];
        foreach ($tiers as $match) {
            $hit = $candidates->first($match);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * The screen as this person sees it: widgets by their rule and by the
     * permission of what they read, in the person's language.
     *
     * @return array<string, mixed>
     */
    public function view(HomeScreen $h, User $user): array
    {
        $doc = $this->present($h);
        $widgets = [];
        foreach ($doc['widgets'] as $w) {
            if (! $this->rules->passes($w['condition'], $user) || ! $this->widgetAllowed($w, $user)) {
                continue;
            }
            if ($w['type'] === 'shortcuts') {
                $w['links'] = array_values(array_filter($w['links'], fn (array $l): bool => $this->access->allows($user, 'form.'.$l['form'].'.'.($l['action'] === 'new' ? 'create' : 'view'))));
                if ($w['links'] === []) {
                    continue;
                }
            }
            $html = $this->translator->pick($w['i18n']['text']);
            $widgets[] = [
                'uuid' => $w['uuid'], 'type' => $w['type'], 'title' => $this->translator->pick($w['i18n']['title']),
                'text' => $html === null ? null : $this->sanitizer->clean($html),
                'report' => $w['report'], 'form' => $w['form'], 'limit' => $w['limit'],
                'links' => array_map(fn (array $l): array => array_diff_key($l, ['i18n' => true]) + ['label' => $this->translator->pick($l['i18n']['label'] ?? [])], $w['links']),
                'layout' => $w['layout'],
            ];
        }

        return ['uuid' => $doc['uuid'], 'label' => $doc['label'], 'widgets' => $widgets];
    }

    /** @param  array<string, mixed>  $w */
    private function widgetAllowed(array $w, User $user): bool
    {
        return match ($w['type']) {
            'report' => $w['report'] !== null && $this->access->allows($user, "report.{$w['report']}.view"),
            'my_records', 'embedded_table' => $w['form'] !== null && Form::query()->where('uuid', $w['form'])->where('state', 'published')->exists()
                && $this->access->allows($user, "form.{$w['form']}.view"),
            default => true,
        };
    }

    private function targetUuid(string $type, ?int $id): ?string
    {
        if ($id === null || $type === 'default') {
            return null;
        }
        $table = ['role' => 'roles', 'department' => 'departments', 'user' => 'users'][$type] ?? null;

        return $table === null ? null : strtolower((string) DB::table($table)->where('id', $id)->value('uuid'));
    }

    /** @return array<string, string> */
    private static function i18n(mixed $values, int $max = 255): array
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
