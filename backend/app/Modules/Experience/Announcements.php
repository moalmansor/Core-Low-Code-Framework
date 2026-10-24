<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Models\Announcement;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use App\Support\Html\HtmlSanitizer;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Announcements and banners (specification §4.29, ADR-0054): system-wide
 * or for one application, shown between a start and an optional end, to
 * everyone or to some roles, as a banner across the top, on home screens or
 * in the notification bell, with a severity. Dismissible ones stay dismissed
 * for the person who dismissed them.
 */
final class Announcements
{
    public const SEVERITIES = ['info', 'success', 'warning', 'danger'];

    public const PLACEMENTS = ['banner', 'home', 'bell'];

    public function __construct(
        private readonly Translator $translator,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /** @return array<string, mixed> */
    public function present(Announcement $a): array
    {
        $doc = [
            'uuid' => strtolower($a->uuid),
            'application' => $a->application_id === null ? null : strtolower((string) Application::query()->whereKey($a->application_id)->value('uuid')),
            'severity' => $a->severity, 'placement' => $a->placement,
            'roles' => DB::table('roles')->whereIn('id', $a->audience_role_ids ?? [])->orderBy('id')->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->values()->all(),
            'starts_at' => Carbon::parse($a->getRawOriginal('starts_at'), 'UTC')->toIso8601ZuluString(),
            'ends_at' => $a->ends_at === null ? null : Carbon::parse($a->getRawOriginal('ends_at'), 'UTC')->toIso8601ZuluString(),
            'dismissible' => $a->is_dismissible, 'published' => $a->is_published,
            'i18n' => ['title' => (object) $a->translationsFor('title'), 'body' => (object) $a->translationsFor('body')],
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['label'] = $this->translator->labelOf($a->translationsFor('title'), __('experience.announcement'));

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
        $title = self::i18n($d['i18n']['title'] ?? []);
        if ($title === []) {
            $errors['i18n.title'][] = __('experience.title_required');
        }
        $body = [];
        foreach (self::i18n($d['i18n']['body'] ?? [], 8000) as $locale => $html) {
            $clean = trim($this->sanitizer->clean($html));
            if ($clean !== '') {
                $body[$locale] = $clean;
            }
        }
        $severity = (string) ($d['severity'] ?? 'info');
        if (! in_array($severity, self::SEVERITIES, true)) {
            $errors['severity'][] = __('experience.bad_severity');
        }
        $placement = (string) ($d['placement'] ?? 'banner');
        if (! in_array($placement, self::PLACEMENTS, true)) {
            $errors['placement'][] = __('experience.bad_placement');
        }
        $application = null;
        if (($d['application'] ?? null) !== null) {
            $application = Str::isUuid((string) $d['application']) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null;
            if ($application === null) {
                $errors['application'][] = __('experience.unknown_application');
            }
        }
        $roles = [];
        foreach (array_slice((array) ($d['roles'] ?? []), 0, 100) as $r) {
            $id = is_string($r) && Str::isUuid($r) ? DB::table('roles')->where('uuid', strtolower($r))->value('id') : null;
            $id === null ? $errors['roles'][] = __('reports.unknown_role') : $roles[] = (int) $id;
        }
        $starts = $this->moment($d['starts_at'] ?? null) ?? Carbon::now('UTC');
        $ends = $this->moment($d['ends_at'] ?? null);
        if (($d['starts_at'] ?? null) !== null && $this->moment($d['starts_at']) === null) {
            $errors['starts_at'][] = __('validation.date', ['attribute' => 'start']);
        }
        if ($ends !== null && $ends->lessThanOrEqualTo($starts)) {
            $errors['ends_at'][] = __('experience.ends_before_start');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'title' => $title, 'body' => $body, 'severity' => $severity, 'placement' => $placement, 'application' => $application,
            'roles' => array_values(array_unique($roles)), 'starts_at' => $starts, 'ends_at' => $ends,
            'dismissible' => (bool) ($d['dismissible'] ?? true), 'published' => (bool) ($d['published'] ?? false),
        ];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?Announcement $existing, array $p, User $actor): Announcement
    {
        return DB::transaction(function () use ($existing, $p, $actor): Announcement {
            $a = $existing ?? new Announcement;
            if ($existing === null) {
                $a->uuid = (string) Str::uuid7();
            }
            $a->forceFill([
                'application_id' => $p['application']?->id, 'severity' => $p['severity'], 'placement' => $p['placement'],
                'audience_role_ids' => $p['roles'] === [] ? null : $p['roles'],
                'starts_at' => $p['starts_at']->format('Y-m-d H:i:s.u'), 'ends_at' => $p['ends_at']?->format('Y-m-d H:i:s.u'),
                'is_dismissible' => $p['dismissible'], 'is_published' => $p['published'],
                'published_by' => $p['published'] ? ($existing?->is_published ? $existing->published_by : $actor->id) : null,
            ])->save();
            $a->setTranslations('title', $p['title']);
            $a->setTranslations('body', $p['body']);
            $this->audit->record($existing === null ? 'announcement.created' : 'announcement.updated', 'config', null, 'announcement', $a->id,
                ['placement' => $a->placement, 'published' => $a->is_published], $actor->id);

            return $a->refresh();
        });
    }

    public function delete(Announcement $a, User $actor): void
    {
        DB::transaction(function () use ($a, $actor): void {
            DB::table('announcement_dismissals')->where('announcement_id', $a->id)->delete();
            $this->audit->record('announcement.deleted', 'config', null, 'announcement', $a->id, ['placement' => $a->placement], $actor->id);
            $a->delete();
        });
    }

    /**
     * What this person sees now: published and in its time window, for one of
     * their roles (or everyone), system-wide or for an application they may
     * use, and not dismissed by them.
     *
     * @return list<array<string, mixed>>
     */
    public function active(User $user, ?string $placement): array
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $roles = $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all();
        $dismissed = DB::table('announcement_dismissals')->where('user_id', $user->id)->pluck('announcement_id')->map(static fn ($v) => (int) $v)->all();
        $apps = Application::query()->where('status', 'active')->get()->keyBy('id');
        $out = [];
        $list = Announcement::query()->where('is_published', true)->where('starts_at', '<=', $now)
            ->where(static fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->when($placement !== null, static fn ($q) => $q->where('placement', $placement))
            ->orderByDesc('starts_at')->orderByDesc('id')->limit(50)->get();
        foreach ($list as $a) {
            if ($a->is_dismissible && in_array((int) $a->id, $dismissed, true)) {
                continue;
            }
            if (($a->audience_role_ids ?? []) !== [] && array_intersect($a->audience_role_ids ?? [], $roles) === []) {
                continue;
            }
            $app = $a->application_id === null ? null : ($apps[$a->application_id] ?? null);
            if ($a->application_id !== null && ($app === null || ! $this->access->allows($user, "app.{$app->uuid}.access"))) {
                continue;
            }
            $body = $this->translator->pick($a->translationsFor('body'));
            $out[] = [
                'uuid' => strtolower($a->uuid), 'title' => $this->translator->pick($a->translationsFor('title')),
                'body' => $body === null ? null : $this->sanitizer->clean($body),
                'severity' => $a->severity, 'placement' => $a->placement, 'dismissible' => $a->is_dismissible,
                'application' => $app === null ? null : ['uuid' => strtolower((string) $app->uuid), 'name' => $this->translator->labelOf($app->translationsFor('name'), (string) $app->key)],
                'starts_at' => Carbon::parse($a->getRawOriginal('starts_at'), 'UTC')->toIso8601ZuluString(),
            ];
        }

        return $out;
    }

    public function dismiss(Announcement $a, User $user): void
    {
        abort_unless($a->is_dismissible, 422, __('experience.not_dismissible'));
        if (! DB::table('announcement_dismissals')->where('announcement_id', $a->id)->where('user_id', $user->id)->exists()) {
            DB::table('announcement_dismissals')->insert(['announcement_id' => $a->id, 'user_id' => $user->id, 'dismissed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        }
    }

    private function moment(mixed $v): ?Carbon
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->utc();
        } catch (\Throwable) {
            return null;
        }
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
