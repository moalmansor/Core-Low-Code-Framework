<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessCache;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Models\Page;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Reports\Models\Report;
use App\Support\Html\HtmlSanitizer;
use App\Support\Json\Canonical;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Custom pages (specification §4.29, ADR-0054). A page belongs to an
 * application and is one of:
 *  - content: rich text per language, sanitized when saved and again when shown;
 *  - link: an https:// address or a path inside the application;
 *  - dashboard, report: shows that object;
 *  - form_host: shows a form's records list directly.
 *
 * Seeing a page needs its own `page.{uuid}.view`, an active application, and
 * for a dashboard, report or form also the permission of what it shows: a
 * page never widens access to what it hosts.
 */
final class Pages
{
    public const TYPES = ['content', 'link', 'dashboard', 'report', 'form_host'];

    private const CONTENT_MAX = 60000;

    public function __construct(
        private readonly Translator $translator,
        private readonly ObjectPermissions $permissions,
        private readonly AccessCache $cache,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /** @return Builder<Page> */
    public static function live(): Builder
    {
        return Page::query()->whereNull('deleted_at');
    }

    /** @return array<string, mixed> */
    public function present(Page $p): array
    {
        $doc = [
            'uuid' => strtolower($p->uuid), 'key' => $p->key, 'type' => $p->type,
            'application' => strtolower((string) Application::query()->whereKey($p->application_id)->value('uuid')),
            'url' => $p->url,
            'dashboard' => $p->dashboard_id === null ? null : strtolower((string) Dashboard::query()->whereKey($p->dashboard_id)->value('uuid')),
            'report' => $p->report_id === null ? null : strtolower((string) Report::query()->whereKey($p->report_id)->value('uuid')),
            'form' => $p->form_id === null ? null : strtolower((string) Form::query()->whereKey($p->form_id)->value('uuid')),
            'i18n' => ['name' => (object) $p->translationsFor('name'), 'content' => (object) $p->translationsFor('content')],
            'published' => $p->is_published,
        ];
        $doc['hash'] = self::hash($doc);
        $doc['label'] = $this->translator->labelOf($p->translationsFor('name'), $p->key);
        $doc['permission'] = 'page.'.$doc['uuid'].'.view';

        return $doc;
    }

    /** @param  array<string, mixed>  $doc */
    public static function hash(array $doc): string
    {
        return hash('sha256', (string) json_encode(Canonical::of(array_diff_key($doc, array_flip(['hash', 'label', 'permission']))), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?Page $existing): array
    {
        $errors = [];
        $application = $existing !== null ? Application::query()->find($existing->application_id)
            : (Str::isUuid((string) ($d['application'] ?? '')) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null);
        if ($application === null) {
            $errors['application'][] = __('experience.unknown_application');
        }
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
            $errors['key'][] = __('experience.bad_key');
        } elseif ($application !== null && self::live()->where('application_id', $application->id)->where('key', $key)
            ->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('experience.key_taken');
        }
        $name = self::i18n($d['i18n']['name'] ?? []);
        if ($name === []) {
            $errors['i18n.name'][] = __('experience.name_required');
        }
        $type = (string) ($d['type'] ?? '');
        if (! in_array($type, self::TYPES, true)) {
            $errors['type'][] = __('experience.bad_type');
        }
        $content = [];
        $url = null;
        $dashboard = $report = $form = null;
        if ($type === 'content') {
            foreach (self::i18n($d['i18n']['content'] ?? [], self::CONTENT_MAX + 1) as $locale => $html) {
                if (mb_strlen($html) > self::CONTENT_MAX) {
                    $errors['i18n.content'][] = __('experience.content_too_long', ['max' => self::CONTENT_MAX]);
                }
                $clean = trim($this->sanitizer->clean($html));
                if ($clean !== '') {
                    $content[$locale] = $clean;
                }
            }
            if ($content === []) {
                $errors['i18n.content'][] = __('experience.content_required');
            }
        } elseif ($type === 'link') {
            $url = trim((string) ($d['url'] ?? ''));
            if (preg_match('#^(https://[^\s/]+|/)[^\s]*$#', $url) !== 1 || str_starts_with($url, '//') || mb_strlen($url) > 2048) {
                $errors['url'][] = __('experience.bad_url');
            }
        } elseif ($type === 'dashboard') {
            $dashboard = Str::isUuid((string) ($d['dashboard'] ?? '')) ? Dashboard::query()->where('uuid', strtolower((string) $d['dashboard']))->first() : null;
            if ($dashboard === null) {
                $errors['dashboard'][] = __('experience.choose_dashboard');
            }
        } elseif ($type === 'report') {
            $report = Str::isUuid((string) ($d['report'] ?? '')) ? Report::query()->where('uuid', strtolower((string) $d['report']))->first() : null;
            if ($report === null) {
                $errors['report'][] = __('experience.choose_report');
            }
        } elseif ($type === 'form_host') {
            $form = Str::isUuid((string) ($d['form'] ?? '')) ? Form::query()->where('uuid', strtolower((string) $d['form']))->whereIn('kind', ['form', 'collection'])->first() : null;
            if ($form === null) {
                $errors['form'][] = __('experience.choose_form');
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'uuid' => $existing->uuid ?? (is_string($d['uuid'] ?? null) && Str::isUuid($d['uuid']) ? strtolower($d['uuid']) : (string) Str::uuid7()),
            'application' => $application, 'key' => $key, 'type' => $type, 'name' => $name, 'content' => $content, 'url' => $url,
            'dashboard' => $dashboard, 'report' => $report, 'form' => $form, 'published' => (bool) ($d['published'] ?? false),
        ];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?Page $existing, array $p, User $actor): Page
    {
        return DB::transaction(function () use ($existing, $p, $actor): Page {
            $page = $existing ?? new Page;
            if ($existing === null) {
                $page->uuid = $p['uuid'];
            }
            $wasPublished = $existing !== null && $existing->is_published;
            $page->forceFill([
                'application_id' => $p['application']->id, 'key' => $p['key'], 'type' => $p['type'], 'url' => $p['url'],
                'dashboard_id' => $p['dashboard']?->id, 'report_id' => $p['report']?->id, 'form_id' => $p['form']?->id,
                'layout' => null, 'is_published' => $p['published'],
                'published_at' => $p['published'] && ! $wasPublished ? Carbon::now('UTC')->format('Y-m-d H:i:s.u') : ($p['published'] ? $page->published_at : null),
            ])->save();
            $page->setTranslations('name', $p['name']);
            $page->setTranslations('content', $p['content']);
            $this->permissions->registerPage($page->id, $page->uuid);
            $this->audit->record($existing === null ? 'page.created' : 'page.updated', 'config', null, 'page', $page->id,
                ['key' => $page->key, 'type' => $page->type, 'published' => $page->is_published], $actor->id);
            $this->cache->bump();

            return $page->refresh();
        });
    }

    /** Removes the page (kept for the audit trail), its menu entries and its permission. */
    public function delete(Page $page, User $actor): void
    {
        DB::transaction(function () use ($page, $actor): void {
            DB::table('menu_items')->where('target_type', 'page')->where('target_id', $page->id)->delete();
            $this->permissions->forget('page.'.strtolower($page->uuid));
            $page->forceFill(['deleted_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'deleted_by' => $actor->id, 'is_published' => false])->save();
            $this->audit->record('page.deleted', 'config', null, 'page', $page->id, ['key' => $page->key], $actor->id);
        });
    }

    /**
     * Whether the person may open the page: published, its application active
     * (or the person may work during maintenance), its own permission, and
     * the permission of what it shows.
     */
    public function viewable(Page $page, User $user): bool
    {
        if (! $page->is_published || $page->deleted_at !== null) {
            return false;
        }
        $app = Application::query()->find($page->application_id);
        if ($app === null || $app->status !== 'active' || ! $this->access->allows($user, 'page.'.strtolower($page->uuid).'.view')) {
            return false;
        }

        return match ($page->type) {
            'dashboard' => ($uuid = Dashboard::query()->whereKey($page->dashboard_id)->where('is_active', true)->value('uuid')) !== null
                && $this->access->allows($user, 'dashboard.'.strtolower((string) $uuid).'.view'),
            'report' => ($uuid = Report::query()->whereKey($page->report_id)->value('uuid')) !== null
                && $this->access->allows($user, 'report.'.strtolower((string) $uuid).'.view'),
            'form_host' => ($form = Form::query()->find($page->form_id)) !== null && $form->state === 'published'
                && $this->access->allows($user, 'form.'.strtolower((string) $form->uuid).'.view'),
            default => true,
        };
    }

    /**
     * What the viewer receives: the page in their language; content sanitized
     * again, because translations can also be edited in the translations manager.
     *
     * @return array<string, mixed>
     */
    public function view(Page $page): array
    {
        $doc = $this->present($page);
        $html = $this->translator->pick($page->translationsFor('content'));

        return [
            'uuid' => $doc['uuid'], 'type' => $page->type, 'label' => $doc['label'], 'application' => $doc['application'],
            'content' => $html === null ? null : $this->sanitizer->clean($html),
            'url' => $page->url, 'dashboard' => $doc['dashboard'], 'report' => $doc['report'], 'form' => $doc['form'],
        ];
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
