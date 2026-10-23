<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessResolver;
use App\Modules\Admin\ConsoleAreas;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Flags\FeatureFlags;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Models\Tour;
use App\Modules\Identity\Models\User;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guided tours and first-use hints (specification §4.37, ADR-0065).
 *
 * A tour belongs to a form (its record screens) or to a place: the home page,
 * My Work, Insights, the profile, or an admin area. Each step points at a
 * field, a group or a named part of the screen, or at nothing (a centred
 * note). A "first use" tour starts by itself the first time someone opens the
 * place; a "manual" one starts from the Help panel. Changing the steps makes
 * a new version, which shows again; a reset shows it again too.
 */
final class Tours
{
    public const TRIGGERS = ['first_use', 'manual'];

    public const PLACES = ['home', 'my_work', 'insights', 'profile'];

    /** Parts of the screen a step can point at; the client marks them with data-tour. */
    public const ANCHORS = ['navigation', 'search', 'notifications', 'help', 'user_menu', 'new_record', 'save_record', 'record_actions'];

    public const PLACEMENTS = ['auto', 'top', 'bottom', 'start', 'end'];

    private const MAX_STEPS = 30;

    public function __construct(
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly AccessResolver $access,
        private readonly FeatureFlags $flags,
    ) {}

    /** @return array<string, mixed> */
    public function present(Tour $tour): array
    {
        $all = $this->translator->allMany('tour', [$tour->id])[$tour->id] ?? [];
        $doc = [
            'uuid' => strtolower($tour->uuid), 'form' => $tour->form_id === null ? null : strtolower((string) DB::table('forms')->where('id', $tour->form_id)->value('uuid')),
            'place' => $tour->route_key, 'trigger' => $tour->trigger, 'active' => $tour->is_active,
            'roles' => DB::table('roles')->whereIn('id', $tour->audience_role_ids ?? [])->orderBy('id')->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->values()->all(),
            'i18n' => ['name' => (object) ($all['name'] ?? [])],
            'steps' => array_map(static fn (array $s) => [
                'key' => $s['step_key'], 'target' => $s['target_selector'], 'placement' => $s['placement'],
                'i18n' => ['title' => (object) ($all["steps.{$s['step_key']}.title"] ?? []), 'body' => (object) ($all["steps.{$s['step_key']}.body"] ?? [])],
            ], $tour->steps),
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['version'] = $tour->version;
        $doc['label'] = $this->translator->labelOf($all['name'] ?? [], __('experience.tour'));
        $doc['completed'] = DB::table('tour_progress')->where('tour_id', $tour->id)->where('version', $tour->version)->whereNotNull('completed_at')->count();

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
        $formId = null;
        $place = null;
        if (($d['form'] ?? null) !== null) {
            $formId = Str::isUuid((string) $d['form']) ? DB::table('forms')->where('uuid', strtolower((string) $d['form']))->where('state', 'published')->value('id') : null;
            if ($formId === null) {
                $errors['form'][] = __('experience.tour_bad_form');
            }
        } else {
            $place = (string) ($d['place'] ?? '');
            $area = str_starts_with($place, 'area.') ? substr($place, 5) : null;
            if (! in_array($place, self::PLACES, true) && ($area === null || ! isset(ConsoleAreas::BUILT[$area]))) {
                $errors['place'][] = __('experience.tour_bad_place');
            }
        }
        $trigger = (string) ($d['trigger'] ?? '');
        if (! in_array($trigger, self::TRIGGERS, true)) {
            $errors['trigger'][] = __('experience.tour_bad_trigger');
        }
        $roles = [];
        foreach (array_slice((array) ($d['roles'] ?? []), 0, 100) as $r) {
            $id = is_string($r) && Str::isUuid($r) ? DB::table('roles')->where('uuid', strtolower($r))->value('id') : null;
            $id === null ? $errors['roles'][] = __('reports.unknown_role') : $roles[] = (int) $id;
        }
        $name = $this->texts($d['i18n']['name'] ?? [], 255);
        if ($name === []) {
            $errors['i18n.name'][] = __('experience.name_required');
        }
        $steps = [];
        $texts = ['name' => $name];
        $list = array_values((array) ($d['steps'] ?? []));
        if ($list === [] || count($list) > self::MAX_STEPS) {
            $errors['steps'][] = __('experience.tour_steps_count', ['max' => self::MAX_STEPS]);
        }
        foreach (array_slice($list, 0, self::MAX_STEPS) as $i => $s) {
            $s = (array) $s;
            $key = (string) ($s['key'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key) !== 1 || isset($texts["steps.{$key}.title"])) {
                $errors["steps.{$i}.key"][] = __('experience.tour_bad_step_key');
            }
            $target = $s['target'] ?? null;
            if ($target !== null && ! $this->validTarget((string) $target, $formId)) {
                $errors["steps.{$i}.target"][] = __('experience.tour_bad_target');
            }
            $placement = (string) ($s['placement'] ?? 'auto');
            if (! in_array($placement, self::PLACEMENTS, true)) {
                $errors["steps.{$i}.placement"][] = __('experience.tour_bad_target');
            }
            $title = $this->texts($s['i18n']['title'] ?? [], 255);
            $body = $this->texts($s['i18n']['body'] ?? [], 1000);
            if ($body === []) {
                $errors["steps.{$i}.body"][] = __('experience.tour_step_text');
            }
            $texts["steps.{$key}.title"] = $title;
            $texts["steps.{$key}.body"] = $body;
            $steps[] = ['step_key' => $key, 'target_selector' => $target === null ? null : (string) $target, 'placement' => $placement];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['form_id' => $formId, 'route_key' => $place, 'trigger' => $trigger, 'audience_role_ids' => array_values(array_unique($roles)),
            'is_active' => (bool) ($d['active'] ?? true), 'steps' => $steps, 'texts' => $texts];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?Tour $existing, array $p, User $actor): Tour
    {
        return DB::transaction(function () use ($existing, $p, $actor): Tour {
            $tour = $existing ?? new Tour;
            $before = $existing === null ? null : $this->present($existing);
            if ($existing === null) {
                $tour->uuid = (string) Str::uuid7();
                $tour->version = 1;
            }
            $tour->forceFill(array_diff_key($p, ['texts' => true]))->save();
            $this->translator->syncObjects('tour', [$tour->id => $p['texts']]);
            $tour->refresh();
            // New or changed steps are a new version: it shows again to everyone.
            if ($before !== null && json_encode($before['steps']) !== json_encode($this->present($tour)['steps'])) {
                $tour->forceFill(['version' => $tour->version + 1])->save();
            }
            $this->audit->record($existing === null ? 'tour.created' : 'tour.updated', 'config', null, 'tour', $tour->id, ['version' => $tour->version], $actor->id);

            return $tour->refresh();
        });
    }

    public function delete(Tour $tour, User $actor): void
    {
        $this->audit->record('tour.deleted', 'config', null, 'tour', $tour->id, [], $actor->id);
        $tour->delete();
    }

    /**
     * Shows the tour again: to everyone, or to the holders of some roles.
     *
     * @param  list<int>  $roleIds
     */
    public function reset(Tour $tour, array $roleIds, User $actor): int
    {
        $q = DB::table('tour_progress')->where('tour_id', $tour->id);
        if ($roleIds !== []) {
            $q->whereIn('user_id', DB::table('user_roles')->whereIn('role_id', $roleIds)->select('user_id'));
        }
        $n = $q->delete();
        $this->audit->record('tour.reset', 'config', null, 'tour', $tour->id, ['roles' => $roleIds, 'people' => $n], $actor->id);

        return $n;
    }

    /**
     * The tours of one place for one person, in their language; `due` marks a
     * first-use tour they have not finished or dismissed in its current version.
     *
     * @return list<array<string, mixed>>
     */
    public function forPlace(User $user, ?string $form, ?string $place): array
    {
        $q = Tour::query()->where('is_active', true);
        if ($form !== null) {
            $f = Str::isUuid($form) ? DB::table('forms')->where('uuid', strtolower($form))->first(['id', 'uuid']) : null;
            if ($f === null || ! $this->access->allows($user, 'form.'.strtolower((string) $f->uuid).'.view') || ! $this->flags->allows($user, 'form', (int) $f->id)) {
                return [];
            }
            $q->where('form_id', $f->id);
        } else {
            $area = $place !== null && str_starts_with($place, 'area.') ? substr($place, 5) : null;
            if ($area !== null && (! isset(ConsoleAreas::BUILT[$area]) || array_filter(ConsoleAreas::BUILT[$area][1], fn ($perm) => $this->access->allows($user, $perm)) === [])) {
                return [];
            }
            $q->whereNull('form_id')->where('route_key', (string) $place);
        }
        $tours = $q->orderBy('id')->get();
        if ($tours->isEmpty()) {
            return [];
        }
        $roles = $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all();
        $progress = DB::table('tour_progress')->where('user_id', $user->id)->whereIn('tour_id', $tours->pluck('id'))->get()->keyBy('tour_id');
        $all = $this->translator->allMany('tour', $tours->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $out = [];
        foreach ($tours as $t) {
            if (($t->audience_role_ids ?? []) !== [] && array_intersect($t->audience_role_ids ?? [], $roles) === []) {
                continue;
            }
            $texts = $all[$t->id] ?? [];
            $mine = $progress[$t->id] ?? null;
            $seen = $mine !== null && (int) $mine->version >= $t->version && ($mine->completed_at !== null || $mine->dismissed_at !== null);
            $out[] = [
                'uuid' => strtolower($t->uuid), 'name' => $this->translator->pick($texts['name'] ?? []), 'trigger' => $t->trigger, 'version' => $t->version,
                'due' => $t->trigger === 'first_use' && ! $seen,
                'steps' => array_map(fn (array $s) => [
                    'key' => $s['step_key'], 'target' => $s['target_selector'], 'placement' => $s['placement'],
                    'title' => $this->translator->pick($texts["steps.{$s['step_key']}.title"] ?? []),
                    'body' => $this->translator->pick($texts["steps.{$s['step_key']}.body"] ?? []),
                ], $t->steps),
            ];
        }

        return $out;
    }

    /** Records that the person finished or closed the tour's current version. */
    public function record(Tour $tour, User $user, string $outcome): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $values = ['version' => $tour->version, 'completed_at' => $outcome === 'completed' ? $now : null, 'dismissed_at' => $outcome === 'dismissed' ? $now : null];
        DB::transaction(static function () use ($tour, $user, $values): void {
            $q = DB::table('tour_progress')->where('tour_id', $tour->id)->where('user_id', $user->id);
            $q->exists() ? $q->update($values) : DB::table('tour_progress')->insert(['tour_id' => $tour->id, 'user_id' => $user->id] + $values);
        });
    }

    /** "Show tips again": forgets the person's own progress. */
    public function resetMine(User $user): void
    {
        DB::table('tour_progress')->where('user_id', $user->id)->delete();
    }

    /** A step points at a field or group of the tour's form, a named part of the screen, or nothing. */
    private function validTarget(string $target, ?int $formId): bool
    {
        if (preg_match('/^\[data-(field|group|tour)="([a-z][a-z0-9_]{0,63})"\]$/', $target, $m) !== 1) {
            return false;
        }

        return match ($m[1]) {
            'tour' => in_array($m[2], self::ANCHORS, true),
            'field' => $formId !== null && DB::table('fields')->where('form_id', $formId)->where('key', $m[2])->exists(),
            default => $formId !== null && DB::table('field_groups')->where('form_id', $formId)->where('key', $m[2])->exists(),
        };
    }

    /** @return array<string, string> plain text per locale */
    private function texts(mixed $values, int $max): array
    {
        $out = [];
        foreach ((array) $values as $locale => $v) {
            if (is_string($locale) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $locale) === 1 && is_string($v) && trim($v) !== '') {
                $out[$locale] = mb_substr(trim(strip_tags($v)), 0, $max);
            }
        }

        return $out;
    }
}
