<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessResolver;
use App\Modules\Admin\ConsoleAreas;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Flags\FeatureFlags;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Models\HelpContent;
use App\Modules\Experience\Models\Page;
use App\Modules\Identity\Models\User;
use App\Support\Html\HtmlSanitizer;
use App\Support\Json\Canonical;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Help content (specification §4.37, ADR-0064).
 *
 * Administrators write help for a form, one of its fields or groups, a
 * page, an application or an admin area, in every language, and choose how
 * it shows: a tooltip beside the field (plain text), an entry in the Help
 * side panel, or a help page of its own. People see published help only,
 * and only for what they may open.
 */
final class HelpContents
{
    public const TARGETS = ['form', 'field', 'group', 'page', 'application', 'admin_area'];

    public const DISPLAYS = ['tooltip', 'side_panel', 'help_page'];

    private const TABLES = ['form' => 'forms', 'field' => 'fields', 'group' => 'field_groups', 'page' => 'pages', 'application' => 'applications'];

    private const BODY_MAX = 20000;

    public function __construct(
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly HtmlSanitizer $sanitizer,
        private readonly AccessResolver $access,
        private readonly Pages $pages,
        private readonly FeatureFlags $flags,
    ) {}

    /** An admin area has no row of its own; its key is stored as a stable number. */
    public static function areaId(string $key): int
    {
        return (int) sprintf('%u', crc32('admin_area:'.$key));
    }

    /** @return array<string, mixed> */
    public function present(HelpContent $h): array
    {
        $doc = [
            'uuid' => strtolower($h->uuid), 'target_type' => $h->target_type, 'target' => $this->target($h), 'form' => $this->formOf($h),
            'display' => $h->display, 'published' => $h->is_published, 'sort_order' => $h->sort_order,
            'i18n' => ['title' => (object) $h->translationsFor('title'), 'body' => (object) $h->translationsFor('body')],
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['label'] = $this->translator->labelOf($h->translationsFor('title'), __('experience.help'));

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d): array
    {
        $errors = [];
        $type = (string) ($d['target_type'] ?? '');
        $display = (string) ($d['display'] ?? '');
        $targetId = null;
        if (! in_array($type, self::TARGETS, true)) {
            $errors['target_type'][] = __('experience.help_bad_target');
        } elseif ($type === 'admin_area') {
            $key = (string) ($d['target'] ?? '');
            isset(ConsoleAreas::BUILT[$key]) ? $targetId = self::areaId($key) : $errors['target'][] = __('experience.help_bad_target');
        } else {
            $uuid = strtolower((string) ($d['target'] ?? ''));
            $targetId = Str::isUuid($uuid) ? DB::table(self::TABLES[$type])->where('uuid', $uuid)->value('id') : null;
            if ($targetId === null) {
                // Fields and groups are found once their form is published.
                $errors['target'][] = __($type === 'field' || $type === 'group' ? 'experience.help_publish_first' : 'experience.help_bad_target');
            }
        }
        if (! in_array($display, self::DISPLAYS, true)) {
            $errors['display'][] = __('experience.help_bad_display');
        } elseif ($display === 'tooltip' && ! in_array($type, ['field', 'group'], true)) {
            $errors['display'][] = __('experience.help_tooltip_fields');
        }
        $title = [];
        foreach ((array) ($d['i18n']['title'] ?? []) as $locale => $text) {
            if (is_string($locale) && is_string($text) && trim($text) !== '') {
                $title[$locale] = mb_substr(trim($text), 0, 255);
            }
        }
        if ($title === []) {
            $errors['i18n.title'][] = __('experience.title_required');
        }
        $body = [];
        foreach ((array) ($d['i18n']['body'] ?? []) as $locale => $html) {
            if (! is_string($locale) || ! is_string($html)) {
                continue;
            }
            if (mb_strlen($html) > self::BODY_MAX) {
                $errors['i18n.body'][] = __('experience.content_too_long', ['max' => self::BODY_MAX]);
            }
            // A tooltip shows plain text; the panel and page show sanitized formatting.
            $clean = $display === 'tooltip' ? trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : trim($this->sanitizer->clean($html));
            if ($clean !== '') {
                $body[$locale] = $display === 'tooltip' ? mb_substr($clean, 0, 500) : $clean;
            }
        }
        if ($body === []) {
            $errors['i18n.body'][] = __('experience.content_required');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['target_type' => $type, 'target_id' => $targetId, 'display' => $display, 'is_published' => (bool) ($d['published'] ?? false),
            'sort_order' => max(0, min(100000, (int) ($d['sort_order'] ?? 0))), 'title' => $title, 'body' => $body];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?HelpContent $existing, array $p, User $actor): HelpContent
    {
        return DB::transaction(function () use ($existing, $p, $actor): HelpContent {
            $h = $existing ?? new HelpContent;
            if ($existing === null) {
                $h->uuid = (string) Str::uuid7();
            }
            $h->forceFill(array_diff_key($p, ['title' => true, 'body' => true]))->save();
            $h->setTranslations('title', $p['title']);
            $h->setTranslations('body', $p['body']);
            $this->audit->record($existing === null ? 'help.created' : 'help.updated', 'config', null, 'help_content', $h->id, ['target' => $h->target_type, 'published' => $h->is_published], $actor->id);

            return $h->refresh();
        });
    }

    public function delete(HelpContent $h, User $actor): void
    {
        $this->audit->record('help.deleted', 'config', null, 'help_content', $h->id, ['target' => $h->target_type], $actor->id);
        $h->delete();
    }

    /**
     * Published help for one thing the person is looking at: a form (with its
     * fields and groups), a page, an application or an admin area.
     *
     * @return array{tooltips: array<string, string>, panel: list<array{uuid: string, title: string|null, body: string|null, display: string, about: string|null}>}
     */
    public function forContext(User $user, string $type, string $target): array
    {
        $out = ['tooltips' => [], 'panel' => []];
        $rows = collect();
        if ($type === 'form') {
            $form = DB::table('forms')->where('uuid', strtolower($target))->first(['id', 'uuid']);
            if ($form === null || ! $this->access->allows($user, 'form.'.strtolower((string) $form->uuid).'.view') || ! $this->flags->allows($user, 'form', (int) $form->id)) {
                return $out;
            }
            $fields = DB::table('fields')->where('form_id', $form->id)->pluck('uuid', 'id');
            $groups = DB::table('field_groups')->where('form_id', $form->id)->pluck('uuid', 'id');
            $rows = HelpContent::query()->where('is_published', true)->where(static fn ($q) => $q
                ->where(static fn ($w) => $w->where('target_type', 'form')->where('target_id', $form->id))
                ->orWhere(static fn ($w) => $w->where('target_type', 'field')->whereIn('target_id', $fields->keys()->all() ?: [0]))
                ->orWhere(static fn ($w) => $w->where('target_type', 'group')->whereIn('target_id', $groups->keys()->all() ?: [0])))
                ->orderBy('sort_order')->orderBy('id')->get();
            $about = static fn (HelpContent $h): ?string => match ($h->target_type) {
                'field' => strtolower((string) ($fields[$h->target_id] ?? '')),
                'group' => strtolower((string) ($groups[$h->target_id] ?? '')),
                default => null,
            };
        } else {
            $id = match ($type) {
                'page' => ($p = Page::query()->where('uuid', strtolower($target))->first()) !== null && $this->pages->viewable($p, $user) ? $p->id : null,
                'application' => ($a = DB::table('applications')->where('uuid', strtolower($target))->first(['id', 'uuid'])) !== null
                    && $this->access->allows($user, 'app.'.strtolower((string) $a->uuid).'.access') ? (int) $a->id : null,
                'admin_area' => isset(ConsoleAreas::BUILT[$target]) && array_filter(ConsoleAreas::BUILT[$target][1], fn ($perm) => $this->access->allows($user, $perm)) !== [] ? self::areaId($target) : null,
                default => null,
            };
            if ($id === null) {
                return $out;
            }
            $rows = HelpContent::query()->where('is_published', true)->where('target_type', $type)->where('target_id', $id)->orderBy('sort_order')->orderBy('id')->get();
            $about = static fn (HelpContent $h): ?string => null;
        }
        foreach ($rows as $h) {
            $body = $this->translator->pick($h->translationsFor('body'));
            if ($h->display === 'tooltip') {
                $key = $about($h);
                if ($key !== null && $body !== null) {
                    $out['tooltips'][$key] = isset($out['tooltips'][$key]) ? $out['tooltips'][$key]."\n".$body : $body;
                }

                continue;
            }
            $out['panel'][] = ['uuid' => strtolower($h->uuid), 'title' => $this->translator->pick($h->translationsFor('title')),
                // A help page opens on its own; the panel shows the others in full.
                'body' => $h->display === 'help_page' || $body === null ? null : $this->sanitizer->clean($body), 'display' => $h->display, 'about' => $about($h)];
        }

        return $out;
    }

    /** A help page, for someone who may see what it is about. @return array<string, mixed>|null */
    public function page(User $user, HelpContent $h): ?array
    {
        if (! $h->is_published || $h->display !== 'help_page') {
            return null;
        }
        [$type, $target] = match ($h->target_type) {
            'field', 'group' => ['form', $this->formOf($h)],
            'admin_area' => ['admin_area', $this->target($h)],
            default => [$h->target_type, $this->target($h)],
        };
        // Shown only where the person may open what the page is about.
        if ($target === null || ! collect($this->forContext($user, $type, $target)['panel'])->contains('uuid', strtolower($h->uuid))) {
            return null;
        }
        $body = $this->translator->pick($h->translationsFor('body'));

        return ['uuid' => strtolower($h->uuid), 'title' => $this->translator->pick($h->translationsFor('title')), 'body' => $body === null ? null : $this->sanitizer->clean($body)];
    }

    /**
     * What help can be written for, for the help editor: published forms,
     * pages, applications and admin areas; with a form, its fields and groups.
     *
     * @return array<string, list<array<string, string|null>>>
     */
    public function targets(?string $form): array
    {
        $label = function (string $type, string $table, string $field, array $extra = []): array {
            $rows = DB::table($table)->when($extra !== [], static fn ($q) => $q->where($extra))->orderBy('key')->limit(2000)->get(['id', 'uuid', 'key']);
            $names = $this->translator->many($type, $rows->pluck('id')->map(static fn ($v) => (int) $v)->all(), [$field]);

            return $rows->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'label' => $names[(int) $r->id][$field] ?? null, 'key' => (string) $r->key])->values()->all();
        };
        if ($form !== null) {
            $formId = Str::isUuid($form) ? DB::table('forms')->where('uuid', strtolower($form))->value('id') : null;
            abort_if($formId === null, 404);

            return ['fields' => $label('field', 'fields', 'label', ['form_id' => $formId]), 'groups' => $label('field_group', 'field_groups', 'title', ['form_id' => $formId])];
        }
        $pages = Pages::live()->orderBy('key')->limit(2000)->get();

        return [
            'forms' => $label('form', 'forms', 'name', ['state' => 'published']),
            'applications' => $label('application', 'applications', 'name'),
            'pages' => $pages->map(fn (Page $p) => ['uuid' => strtolower($p->uuid), 'label' => $this->translator->pick($p->translationsFor('name')), 'key' => $p->key])->values()->all(),
            'admin_areas' => array_map(static fn (string $k) => ['uuid' => $k, 'label' => null, 'key' => $k], array_keys(ConsoleAreas::BUILT)),
        ];
    }

    private function target(HelpContent $h): ?string
    {
        if ($h->target_type === 'admin_area') {
            foreach (array_keys(ConsoleAreas::BUILT) as $key) {
                if (self::areaId($key) === (int) $h->target_id) {
                    return $key;
                }
            }

            return null;
        }
        $uuid = DB::table(self::TABLES[$h->target_type])->where('id', $h->target_id)->value('uuid');

        return $uuid === null ? null : strtolower((string) $uuid);
    }

    /** The form a field, group or form help belongs to. */
    private function formOf(HelpContent $h): ?string
    {
        $formId = match ($h->target_type) {
            'form' => $h->target_id,
            'field' => DB::table('fields')->where('id', $h->target_id)->value('form_id'),
            'group' => DB::table('field_groups')->where('id', $h->target_id)->value('form_id'),
            default => null,
        };

        return $formId === null ? null : strtolower((string) DB::table('forms')->where('id', $formId)->value('uuid'));
    }
}
