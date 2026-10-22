<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Access\AccessCache;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Reports\Models\Report;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dashboards (specification §4.18, ADR-0053): a grid of widgets, saved whole
 * with a concurrency hash, each dashboard with its own permission
 * `dashboard.{uuid}.view`.
 *
 * A widget shows a report (with its own title) or a block of text. Its
 * place is its order and its size (width in twelfths of the row, height in
 * rows); on a phone every widget takes the full width. A widget can be
 * limited to some roles; a report widget is shown only to people who may
 * also view that report.
 */
final class Dashboards
{
    public const TYPES = ['report', 'text'];

    public function __construct(
        private readonly Translator $translator,
        private readonly ObjectPermissions $permissions,
        private readonly AccessCache $cache,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array<string, mixed> */
    public function present(Dashboard $d): array
    {
        $app = $d->application_id === null ? null : Application::query()->find($d->application_id);
        $widgets = DB::table('dashboard_widgets')->where('dashboard_id', $d->id)->orderBy('sort_order')->orderBy('id')->get();
        $reports = DB::table('reports')->whereIn('id', $widgets->pluck('report_id')->filter()->all())->pluck('uuid', 'id');
        $doc = [
            'uuid' => strtolower($d->uuid), 'key' => $d->key, 'application' => $app === null ? null : strtolower($app->uuid), 'active' => $d->is_active,
            'i18n' => ['name' => (object) $d->translationsFor('name'), 'description' => (object) $d->translationsFor('description')],
            'widgets' => $widgets->map(static function (object $w) use ($reports): array {
                $config = json_decode((string) $w->config, true) ?: [];
                $layout = json_decode((string) $w->layout, true) ?: [];

                return [
                    'uuid' => strtolower((string) $w->uuid), 'type' => $w->type, 'report' => $w->report_id === null ? null : strtolower((string) ($reports[$w->report_id] ?? '')),
                    'i18n' => ['title' => (object) ($config['title'] ?? []), 'text' => (object) ($config['text'] ?? [])],
                    'roles' => array_values((array) ($config['roles'] ?? [])), 'layout' => ['w' => (int) ($layout['w'] ?? 6), 'h' => (int) ($layout['h'] ?? 2)],
                ];
            })->values()->all(),
        ];
        $doc['hash'] = self::hash($doc);
        $doc['label'] = $this->translator->labelOf($d->translationsFor('name'), $d->key);

        return $doc;
    }

    /** @param  array<string, mixed>  $doc */
    public static function hash(array $doc): string
    {
        return hash('sha256', (string) json_encode(Canonical::of(array_diff_key($doc, array_flip(['hash', 'label']))), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?Dashboard $existing): array
    {
        $errors = [];
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
            $errors['key'][] = __('reports.bad_key');
        } elseif (Dashboard::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('reports.key_taken');
        }
        $name = self::i18n($d['i18n']['name'] ?? []);
        if ($name === []) {
            $errors['i18n.name'][] = __('reports.name_required');
        }
        $application = null;
        if (($d['application'] ?? null) !== null) {
            $application = Str::isUuid((string) $d['application']) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null;
            if ($application === null) {
                $errors['application'][] = __('reports.unknown_application');
            }
        }
        $widgets = [];
        foreach (array_values((array) ($d['widgets'] ?? [])) as $i => $w) {
            $p = "widgets.{$i}";
            $type = (string) ($w['type'] ?? '');
            if (! in_array($type, self::TYPES, true)) {
                $errors["{$p}.type"][] = __('validation.in', ['attribute' => 'type']);

                continue;
            }
            $report = null;
            if ($type === 'report') {
                $report = Str::isUuid((string) ($w['report'] ?? '')) ? Report::query()->where('uuid', strtolower((string) $w['report']))->first() : null;
                if ($report === null) {
                    $errors["{$p}.report"][] = __('reports.choose_report');
                }
            }
            $text = self::i18n($w['i18n']['text'] ?? [], 4000);
            if ($type === 'text' && $text === []) {
                $errors["{$p}.text"][] = __('reports.text_required');
            }
                $roles = [];
            foreach ((array) ($w['roles'] ?? []) as $r) {
                if (is_string($r) && Str::isUuid($r) && DB::table('roles')->where('uuid', strtolower($r))->exists()) {
                    $roles[] = strtolower($r);
                } else {
                    $errors["{$p}.roles"][] = __('reports.unknown_role');
                }
            }
            $wd = (int) ($w['layout']['w'] ?? 6);
            $ht = (int) ($w['layout']['h'] ?? 2);
            if ($wd < 2 || $wd > 12 || $ht < 1 || $ht > 6) {
                $errors["{$p}.layout"][] = __('reports.bad_size');
            }
            $widgets[] = [
                'uuid' => is_string($w['uuid'] ?? null) && Str::isUuid($w['uuid']) ? strtolower($w['uuid']) : (string) Str::uuid7(), 'type' => $type, 'report' => $report,
                'title' => self::i18n($w['i18n']['title'] ?? []), 'text' => $type === 'text' ? $text : [], 'roles' => array_values(array_unique($roles)),
                'layout' => ['w' => max(2, min(12, $wd)), 'h' => max(1, min(6, $ht))],
            ];
        }
        if (count($widgets) > 40) {
            $errors['widgets'][] = __('reports.too_many', ['max' => 40]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'uuid' => $existing->uuid ?? (is_string($d['uuid'] ?? null) && Str::isUuid($d['uuid']) ? strtolower($d['uuid']) : (string) Str::uuid7()),
            'key' => $key, 'name' => $name, 'description' => self::i18n($d['i18n']['description'] ?? []), 'application' => $application,
            'active' => (bool) ($d['active'] ?? true), 'widgets' => $widgets,
        ];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?Dashboard $existing, array $p, User $actor): Dashboard
    {
        return DB::transaction(function () use ($existing, $p, $actor): Dashboard {
            $d = $existing ?? new Dashboard;
            if ($existing === null) {
                $d->uuid = $p['uuid'];
            }
            $d->forceFill(['key' => $p['key'], 'application_id' => $p['application']?->id, 'layout' => ['columns' => 12], 'is_active' => $p['active']])->save();
            $d->setTranslations('name', $p['name']);
            $d->setTranslations('description', $p['description']);
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            foreach ($p['widgets'] as $i => $w) {
                $row = [
                    'updated_at' => $now, 'type' => $w['type'], 'report_id' => $w['report']?->id, 'sort_order' => $i,
                    'config' => json_encode(['title' => (object) $w['title'], 'text' => (object) $w['text'], 'roles' => $w['roles']], JSON_UNESCAPED_UNICODE),
                    'layout' => json_encode($w['layout']),
                ];
                $id = DB::table('dashboard_widgets')->where('dashboard_id', $d->id)->where('uuid', $w['uuid'])->value('id');
                if ($id === null) {
                    $id = DB::table('dashboard_widgets')->insertGetId($row + ['uuid' => $w['uuid'], 'created_at' => $now, 'dashboard_id' => $d->id]);
                } else {
                    DB::table('dashboard_widgets')->where('id', $id)->update($row);
                }
                $kept[] = (int) $id;
            }
            DB::table('dashboard_widgets')->where('dashboard_id', $d->id)->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
            $this->permissions->registerDashboard($d->id, $d->uuid);
            $this->audit->record($existing === null ? 'dashboard.created' : 'dashboard.updated', 'config', null, 'dashboard', $d->id,
                ['key' => $d->key, 'widgets' => count($p['widgets'])], $actor->id);
            $this->cache->bump();

            return $d->refresh();
        });
    }

    public function delete(Dashboard $d, User $actor): void
    {
        DB::transaction(function () use ($d, $actor): void {
            DB::table('menu_items')->where('target_type', 'dashboard')->where('target_id', $d->id)->delete();
            $this->permissions->forget('dashboard.'.strtolower($d->uuid));
            $this->audit->record('dashboard.deleted', 'config', null, 'dashboard', $d->id, ['key' => $d->key], $actor->id);
            DB::table('dashboard_widgets')->where('dashboard_id', $d->id)->delete();
            $d->delete();
        });
    }

    /**
     * The widgets a person sees: those for their roles, and report widgets only for reports they may view.
     *
     * @param  array<string, mixed>  $doc  from present()
     * @return list<array<string, mixed>>
     */
    public function visibleWidgets(array $doc, User $user): array
    {
        $mine = $user->roles()->pluck('roles.uuid')->map(static fn ($u) => strtolower((string) $u))->all();

        return array_values(array_filter($doc['widgets'], function (array $w) use ($mine, $user): bool {
            if ($w['roles'] !== [] && array_intersect($w['roles'], $mine) === []) {
                return false;
            }

            return $w['type'] !== 'report' || ($w['report'] !== null && $this->access->allows($user, "report.{$w['report']}.view"));
        }));
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
