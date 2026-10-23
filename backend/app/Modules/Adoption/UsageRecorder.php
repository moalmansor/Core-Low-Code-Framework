<?php

declare(strict_types=1);

namespace App\Modules\Adoption;

use App\Modules\Access\AccessResolver;
use App\Modules\Core\Flags\FeatureFlags;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Usage insight (specification §4.37, ADR-0066): which forms are used, by
 * whom, how often, where people abandon a form and which fields they leave
 * empty, counted per day in `usage_metrics`.
 *
 * Submissions, empty fields, page views and searches are counted on the
 * server where they happen; views, starts and abandons come from the
 * screens in batches. System Settings › Adoption decides whether people are
 * named, counted anonymously, or not counted at all.
 */
final class UsageRecorder
{
    public const METRICS = ['form_view', 'form_start', 'form_submit', 'form_abandon', 'field_left_empty', 'page_view', 'search'];

    /** What the screens may report; the rest is counted on the server. */
    public const CLIENT_METRICS = ['form_view', 'form_start', 'form_abandon'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly TenantContext $tenant,
        private readonly AccessResolver $access,
        private readonly FeatureFlags $flags,
        private readonly Translator $translator,
    ) {}

    public function mode(): string
    {
        return (string) $this->settings->get('adoption', 'usage_tracking');
    }

    /** Adds to one day's count of one metric. Never fails the request it is part of. */
    public function count(User $user, string $metric, ?int $formId = null, ?int $fieldId = null, int $n = 1, ?Carbon $at = null): void
    {
        $mode = $this->mode();
        if ($mode === 'off' || $n < 1 || ! in_array($metric, self::METRICS, true)) {
            return;
        }
        $org = $this->tenant->organizationId();
        $userId = $mode === 'people' ? $user->id : null;
        $day = ($at ?? Carbon::now('UTC'))->toDateString();
        $dims = hash('sha256', implode('|', [$org, $metric, $formId ?? '', $fieldId ?? '', $userId ?? '']));
        try {
            $match = DB::table('usage_metrics')->where('day', $day)->where('dims_hash', $dims);
            if ($match->increment('count', $n) === 0) {
                try {
                    DB::table('usage_metrics')->insert(['organization_id' => $org, 'day' => $day, 'metric' => $metric, 'form_id' => $formId, 'field_id' => $fieldId,
                        'user_id' => $userId, 'dims_hash' => $dims, 'count' => $n]);
                } catch (UniqueConstraintViolationException) {
                    // Another request counted the same thing first.
                    DB::table('usage_metrics')->where('day', $day)->where('dims_hash', $dims)->increment('count', $n);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Events the screens send: views, starts and abandons of forms the
     * person may open. Anything else is ignored.
     *
     * @param  list<array{metric?: mixed, form?: mixed}>  $events
     */
    public function fromClient(User $user, array $events): int
    {
        $counted = 0;
        $tally = [];
        foreach (array_slice($events, 0, 50) as $e) {
            $metric = (string) ($e['metric'] ?? '');
            $form = strtolower((string) ($e['form'] ?? ''));
            if (in_array($metric, self::CLIENT_METRICS, true) && Str::isUuid($form)) {
                $tally["{$metric}|{$form}"] = ($tally["{$metric}|{$form}"] ?? 0) + 1;
            }
        }
        foreach ($tally as $key => $n) {
            [$metric, $form] = explode('|', $key);
            $id = DB::table('forms')->where('uuid', $form)->value('id');
            if ($id === null || ! $this->access->allows($user, "form.{$form}.view") || ! $this->flags->allows($user, 'form', (int) $id)) {
                continue;
            }
            $this->count($user, $metric, (int) $id, null, min($n, 50));
            $counted += $n;
        }

        return $counted;
    }

    /**
     * A record was created: one submission, and one "left empty" for every
     * stored field the person could see and did not fill in (repeater rows
     * are not counted).
     *
     * @param  array<string, mixed>  $values  the stored record's values by key
     * @param  array<string, string>  $levels  field uuid => access level
     */
    public function submitted(User $user, FormRuntime $rt, array $values, array $levels): void
    {
        if ($this->mode() === 'off') {
            return;
        }
        $this->count($user, 'form_submit', $rt->form->id);
        $empty = [];
        foreach ($rt->keys as $key => $uuid) {
            if (! isset($rt->columns[$uuid]) || ($levels[$uuid] ?? 'hidden') === 'hidden' || isset($rt->repeaters[$uuid])) {
                continue;
            }
            $v = $values[$key] ?? null;
            if ($v === null || $v === '' || $v === []) {
                $empty[] = strtolower($uuid);
            }
        }
        if ($empty !== []) {
            foreach (DB::table('fields')->where('form_id', $rt->form->id)->whereIn('uuid', $empty)->pluck('id') as $fieldId) {
                $this->count($user, 'field_left_empty', $rt->form->id, (int) $fieldId);
            }
        }
    }

    /**
     * Each form's use between two days: views, starts, submissions,
     * abandons, the people who used it and the last day it was used.
     *
     * @return list<array<string, mixed>>
     */
    public function forms(string $from, string $to): array
    {
        $rows = DB::table('usage_metrics')->whereBetween('day', [$from, $to])->whereNotNull('form_id')->whereIn('metric', ['form_view', 'form_start', 'form_submit', 'form_abandon'])
            ->groupBy('form_id', 'metric')->selectRaw('form_id, metric, SUM('.self::col('count').') AS total, MAX('.self::col('day').') AS last_day')->get();
        $people = DB::table('usage_metrics')->whereBetween('day', [$from, $to])->whereNotNull('form_id')->whereNotNull('user_id')
            ->groupBy('form_id')->selectRaw('form_id, COUNT(DISTINCT user_id) AS people')->pluck('people', 'form_id');
        $forms = DB::table('forms')->whereIn('state', ['published', 'unpublished'])->get(['id', 'uuid', 'key']);
        $names = $this->translator->many('form', $forms->pluck('id')->map(static fn ($v) => (int) $v)->all(), ['name']);
        $out = [];
        foreach ($forms as $f) {
            $mine = $rows->where('form_id', $f->id);
            $sum = static fn (string $m): int => (int) ($mine->firstWhere('metric', $m)->total ?? 0);
            $last = $mine->max('last_day');
            $submits = $sum('form_submit');
            $starts = $sum('form_start');
            $out[] = [
                'uuid' => strtolower((string) $f->uuid), 'key' => (string) $f->key, 'name' => $names[(int) $f->id]['name'] ?? null,
                'views' => $sum('form_view'), 'starts' => $starts, 'submits' => $submits, 'abandons' => $sum('form_abandon'),
                // Of those who started filling it in, the share who submitted (null until someone starts).
                'completion' => $starts > 0 ? round(min(1, $submits / $starts), 3) : null,
                'people' => (int) ($people[$f->id] ?? 0), 'last_used' => $last === null ? null : substr((string) $last, 0, 10),
            ];
        }
        usort($out, static fn ($a, $b) => [$b['views'] + $b['submits'], $a['key']] <=> [$a['views'] + $a['submits'], $b['key']]);

        return $out;
    }

    /**
     * One form in detail: use per day, the people who used it most, and the
     * fields left empty most often (as a share of submissions).
     *
     * @return array<string, mixed>
     */
    public function form(int $formId, string $from, string $to): array
    {
        $base = static fn () => DB::table('usage_metrics')->where('form_id', $formId)->whereBetween('day', [$from, $to]);
        $daily = $base()->whereIn('metric', ['form_view', 'form_start', 'form_submit', 'form_abandon'])->groupBy('day', 'metric')
            ->selectRaw(self::col('day').' AS day, metric, SUM('.self::col('count').') AS total')->orderBy('day')->get()
            ->groupBy(static fn ($r) => substr((string) $r->day, 0, 10))
            ->map(static fn ($g, $day) => ['day' => $day] + collect(['form_view', 'form_start', 'form_submit', 'form_abandon'])
                ->mapWithKeys(static fn ($m) => [$m => (int) ($g->firstWhere('metric', $m)->total ?? 0)])->all())
            ->values()->all();
        $people = $base()->whereNotNull('user_id')->whereIn('metric', ['form_view', 'form_submit'])->groupBy('user_id', 'metric')
            ->selectRaw('user_id, metric, SUM('.self::col('count').') AS total')->get()->groupBy('user_id')
            ->map(static fn ($g, $id) => ['user_id' => (int) $id, 'views' => (int) ($g->firstWhere('metric', 'form_view')->total ?? 0), 'submits' => (int) ($g->firstWhere('metric', 'form_submit')->total ?? 0)])
            ->sortByDesc(static fn ($p) => $p['submits'] * 1000000 + $p['views'])->take(20)->values();
        $users = DB::table('users')->whereIn('id', $people->pluck('user_id'))->pluck('name', 'id');
        $submits = (int) $base()->where('metric', 'form_submit')->sum('count');
        $empty = $base()->where('metric', 'field_left_empty')->whereNotNull('field_id')->groupBy('field_id')->selectRaw('field_id, SUM('.self::col('count').') AS total')->get();
        $fields = DB::table('fields')->whereIn('id', $empty->pluck('field_id'))->get(['id', 'uuid', 'key']);
        $labels = $this->translator->many('field', $fields->pluck('id')->map(static fn ($v) => (int) $v)->all(), ['label']);

        return [
            'daily' => $daily,
            'people' => $people->map(static fn ($p) => ['name' => $users[$p['user_id']] ?? null, 'views' => $p['views'], 'submits' => $p['submits']])->all(),
            'anonymous' => $this->mode() !== 'people',
            'submits' => $submits,
            'empty_fields' => $empty->map(static function ($r) use ($fields, $labels, $submits) {
                $f = $fields->firstWhere('id', $r->field_id);

                return ['uuid' => $f === null ? null : strtolower((string) $f->uuid), 'key' => $f->key ?? null, 'label' => $labels[(int) $r->field_id]['label'] ?? null,
                    'count' => (int) $r->total, 'share' => $submits > 0 ? round(min(1, (int) $r->total / $submits), 3) : null];
            })->sortByDesc('count')->values()->all(),
        ];
    }

    /** A column name quoted for the engine: `count` and `day` are also function names. */
    private static function col(string $name): string
    {
        return DB::getQueryGrammar()->wrap($name);
    }

    /** Forgets counts older than the retention System Settings keep. */
    public function prune(): int
    {
        $days = (int) $this->settings->get('adoption', 'usage_retention_days');

        return DB::table('usage_metrics')->where('day', '<', Carbon::now('UTC')->subDays($days)->toDateString())->delete();
    }
}
