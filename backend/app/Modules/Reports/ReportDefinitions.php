<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Access\AccessCache;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Downloads\DownloadPaths;
use App\Modules\Downloads\DownloadProfiles;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Reports\Models\Report;
use App\Support\Json\Canonical;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Report definitions (specification §4.18, ADR-0053): checked against the
 * published base form, saved whole with a concurrency hash, each with its
 * own permission `report.{uuid}.view`.
 *
 * A definition has dimensions (what to group by, with a date bucket for
 * dates), measures (count, sum, average, minimum, maximum), detail columns
 * (a list of records when nothing is grouped), fixed filters, a sort, a
 * limit and display options. Every path reads through relations exactly as
 * a download profile does (DownloadPaths), so the same access rules apply.
 */
final class ReportDefinitions
{
    public const VISUALIZATIONS = ['table', 'pivot', 'bar', 'line', 'pie', 'area', 'kpi'];

    public const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max'];

    public const BUCKETS = ['day', 'week', 'month', 'quarter', 'year'];

    public const NUMERIC = ['decimal', 'int', 'currency', 'duration', 'formula'];

    public const DATES = ['date', 'datetime'];

    private const DATE_SYSTEM = ['created_at', 'updated_at', 'last_transition_at'];

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly DownloadPaths $paths,
        private readonly Translator $translator,
        private readonly ObjectPermissions $permissions,
        private readonly AccessCache $cache,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * The definition as the builder edits it.
     *
     * @return array<string, mixed>
     */
    public function present(Report $r): array
    {
        $form = Form::query()->find($r->base_form_id);
        $app = $r->application_id === null ? null : Application::query()->find($r->application_id);
        $doc = [
            'uuid' => strtolower($r->uuid), 'key' => $r->key, 'form' => $form === null ? null : strtolower($form->uuid),
            'application' => $app === null ? null : strtolower($app->uuid), 'visualization' => $r->visualization,
            'i18n' => ['name' => (object) $r->translationsFor('name'), 'description' => (object) $r->translationsFor('description')],
            'cacheSeconds' => $r->cache_ttl_seconds,
        ] + $r->definition;
        $doc['hash'] = self::hash($doc);
        $doc['label'] = $this->translator->labelOf($r->translationsFor('name'), $r->key);
        $doc['form_name'] = $form === null ? null : $this->translator->labelOf($form->translationsFor('name'), (string) $form->key);

        return $doc;
    }

    /** @param  array<string, mixed>  $doc */
    public static function hash(array $doc): string
    {
        return hash('sha256', (string) json_encode(Canonical::of(array_diff_key($doc, array_flip(['hash', 'label', 'form_name']))), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Checks a definition and returns it normalized.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?Report $existing): array
    {
        $errors = [];
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
            $errors['key'][] = __('reports.bad_key');
        } elseif (Report::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('reports.key_taken');
        }
        $form = Str::isUuid((string) ($d['form'] ?? '')) ? Form::query()->where('uuid', strtolower((string) $d['form']))->first() : null;
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null) {
            $errors['form'][] = __('reports.form_required');

            throw ValidationException::withMessages($errors);
        }
        $application = null;
        if (($d['application'] ?? null) !== null) {
            $application = Str::isUuid((string) $d['application']) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null;
            if ($application === null) {
                $errors['application'][] = __('reports.unknown_application');
            }
        }
        $viz = (string) ($d['visualization'] ?? 'table');
        if (! in_array($viz, self::VISUALIZATIONS, true)) {
            $errors['visualization'][] = __('validation.in', ['attribute' => 'visualization']);
        }
        $name = self::i18n($d['i18n']['name'] ?? []);
        if ($name === []) {
            $errors['i18n.name'][] = __('reports.name_required');
        }

        $dimensions = [];
        foreach (array_values((array) ($d['dimensions'] ?? [])) as $i => $x) {
            $p = "dimensions.{$i}";
            $path = self::path($x['path'] ?? []);
            $r = $path === [] ? null : $this->paths->resolve($rt, $path);
            if ($r === null || $r['repeater'] !== null) {
                $errors["{$p}.path"][] = __('reports.bad_path');

                continue;
            }
            $bucket = $x['bucket'] ?? null;
            if ($bucket !== null && (! in_array($bucket, self::BUCKETS, true) || ! $this->isDate($r))) {
                $errors["{$p}.bucket"][] = __('reports.bucket_dates_only');
            }
            $dimensions[] = ['uuid' => self::uuid($x['uuid'] ?? null), 'path' => $path, 'bucket' => $bucket, 'i18n' => ['label' => (object) self::i18n($x['i18n']['label'] ?? [])]];
        }
        if (count($dimensions) > 3) {
            $errors['dimensions'][] = __('reports.too_many', ['max' => 3]);
        }

        $measures = [];
        foreach (array_values((array) ($d['measures'] ?? [])) as $i => $x) {
            $p = "measures.{$i}";
            $agg = (string) ($x['aggregate'] ?? '');
            if (! in_array($agg, self::AGGREGATES, true)) {
                $errors["{$p}.aggregate"][] = __('validation.in', ['attribute' => 'aggregate']);

                continue;
            }
            $path = self::path($x['path'] ?? []);
            if ($path === [] && $agg !== 'count') {
                $errors["{$p}.path"][] = __('reports.measure_needs_field');

                continue;
            }
            if ($path !== []) {
                $r = $this->paths->resolve($rt, $path);
                if ($r === null) {
                    $errors["{$p}.path"][] = __('reports.bad_path');

                    continue;
                }
                $numeric = $this->isNumeric($r);
                if (in_array($agg, ['sum', 'avg'], true) && ! $numeric) {
                    $errors["{$p}.path"][] = __('reports.numbers_only');
                } elseif (in_array($agg, ['min', 'max'], true) && ! $numeric && ! $this->isDate($r)) {
                    $errors["{$p}.path"][] = __('reports.numbers_or_dates');
                }
            }
            $measures[] = ['uuid' => self::uuid($x['uuid'] ?? null), 'aggregate' => $agg, 'path' => $path === [] ? null : $path, 'i18n' => ['label' => (object) self::i18n($x['i18n']['label'] ?? [])]];
        }
        if (count($measures) > 8) {
            $errors['measures'][] = __('reports.too_many', ['max' => 8]);
        }

        $columns = [];
        foreach (array_values((array) ($d['columns'] ?? [])) as $i => $x) {
            $path = self::path($x['path'] ?? []);
            if ($path === [] || $this->paths->resolve($rt, $path) === null) {
                $errors["columns.{$i}.path"][] = __('reports.bad_path');

                continue;
            }
            $columns[] = ['uuid' => self::uuid($x['uuid'] ?? null), 'path' => $path, 'i18n' => ['header' => (object) self::i18n($x['i18n']['header'] ?? [])]];
        }
        if (count($columns) > 50) {
            $errors['columns'][] = __('reports.too_many', ['max' => 50]);
        }

        // What each visualization needs.
        $nd = count($dimensions);
        $nm = count($measures);
        $shape = match ($viz) {
            'table' => $columns !== [] ? ($nd === 0 && $nm === 0) : $nm >= 1,
            'pivot' => $nd === 2 && $nm >= 1 && $columns === [],
            'bar', 'line', 'area' => $nd >= 1 && $nd <= 2 && $nm >= 1 && ($nd === 1 || $nm === 1) && $columns === [],
            'pie' => $nd === 1 && $nm === 1 && $columns === [],
            'kpi' => $nd === 0 && $nm >= 1 && $nm <= 4 && $columns === [],
            default => true,
        };
        if (! $shape) {
            $errors['visualization'][] = __("reports.shape.{$viz}");
        }

        $filters = [];
        foreach (array_values((array) ($d['filters'] ?? [])) as $i => $f) {
            $p = "filters.{$i}";
            $path = self::path($f['path'] ?? []);
            $r = $path === [] ? null : $this->paths->resolve($rt, $path);
            $forward = $r !== null && $r['field'] !== null && $r['repeater'] === null && array_filter($r['hops'], static fn ($h) => ! in_array($h['kind'], ['ref', 'multi'], true)) === [];
            if (! $forward) {
                $errors["{$p}.path"][] = __('downloads.bad_filter_path');
            }
            $op = $f['operator'] ?? null;
            if (! in_array($op, DownloadProfiles::OPERATORS, true)) {
                $errors["{$p}.operator"][] = __('validation.in', ['attribute' => 'operator']);
            }
            $value = in_array($op, ['empty', 'not_empty'], true) ? null : ($f['value'] ?? null);
            if (! in_array($op, ['empty', 'not_empty'], true) && ($value === null || $value === '' || $value === []
                || (! is_scalar($value) && ! (is_array($value) && array_is_list($value) && count($value) <= 200)))) {
                $errors["{$p}.value"][] = __('downloads.value_required');
            }
            $filters[] = ['uuid' => self::uuid($f['uuid'] ?? null), 'path' => $path, 'operator' => $op, 'value' => $value];
        }
        if (count($filters) > 30) {
            $errors['filters'][] = __('reports.too_many', ['max' => 30]);
        }

        $sort = null;
        if (is_array($d['sort'] ?? null) && ($d['sort']['by'] ?? null) !== null) {
            $by = (string) $d['sort']['by'];
            $refs = match ($by) {
                'dimension' => array_column($dimensions, 'uuid'),
                'measure' => array_column($measures, 'uuid'),
                'column' => array_column($columns, 'uuid'),
                default => [],
            };
            $ref = strtolower((string) ($d['sort']['ref'] ?? ''));
            if (! in_array($ref, $refs, true)) {
                $errors['sort.ref'][] = __('reports.bad_sort');
            }
            $sort = ['by' => $by, 'ref' => $ref, 'direction' => ($d['sort']['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc'];
        }
        $limit = ($d['limit'] ?? null) === null || $d['limit'] === '' ? null : (int) $d['limit'];
        if ($limit !== null && ($limit < 1 || $limit > 10000)) {
            $errors['limit'][] = __('validation.between.numeric', ['attribute' => 'limit', 'min' => 1, 'max' => 10000]);
        }
        $cache = ($d['cacheSeconds'] ?? null) === null || $d['cacheSeconds'] === '' ? null : (int) $d['cacheSeconds'];
        if ($cache !== null && ($cache < 0 || $cache > 86400)) {
            $errors['cacheSeconds'][] = __('validation.between.numeric', ['attribute' => 'cacheSeconds', 'min' => 0, 'max' => 86400]);
        }
        $o = (array) ($d['options'] ?? []);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'uuid' => $existing->uuid ?? self::uuid($d['uuid'] ?? null), 'key' => $key, 'form' => $form, 'application' => $application, 'visualization' => $viz,
            'name' => $name, 'description' => self::i18n($d['i18n']['description'] ?? []), 'cacheSeconds' => $cache,
            'definition' => [
                'dimensions' => $dimensions, 'measures' => $measures, 'columns' => $columns, 'filters' => $filters, 'sort' => $sort, 'limit' => $limit,
                'options' => [
                    'stacked' => (bool) ($o['stacked'] ?? false), 'showValues' => (bool) ($o['showValues'] ?? false),
                    'showLegend' => (bool) ($o['showLegend'] ?? true), 'showTotals' => (bool) ($o['showTotals'] ?? false),
                ],
            ],
        ];
    }

    /**
     * Saves a checked definition.
     *
     * @param  array<string, mixed>  $p  from check()
     */
    public function save(?Report $existing, array $p, User $actor): Report
    {
        return DB::transaction(function () use ($existing, $p, $actor): Report {
            $r = $existing ?? new Report;
            if ($existing === null) {
                $r->uuid = $p['uuid'];
            }
            $r->forceFill([
                'key' => $p['key'], 'base_form_id' => $p['form']->id, 'application_id' => $p['application']->id ?? $p['form']->application_id,
                'visualization' => $p['visualization'], 'definition' => $p['definition'], 'cache_ttl_seconds' => $p['cacheSeconds'], 'is_personal' => false,
            ])->save();
            $r->setTranslations('name', $p['name']);
            $r->setTranslations('description', $p['description']);
            $this->permissions->registerReport($r->id, $r->uuid);
            $this->audit->record($existing === null ? 'report.created' : 'report.updated', 'config', null, 'report', $r->id,
                ['key' => $r->key, 'visualization' => $r->visualization], $actor->id, null, $p['form']->id);
            $this->cache->bump();

            return $r->refresh();
        });
    }

    public function delete(Report $r, User $actor): void
    {
        DB::transaction(function () use ($r, $actor): void {
            DB::table('dashboard_widgets')->where('report_id', $r->id)->delete();
            DB::table('menu_items')->where('target_type', 'report')->where('target_id', $r->id)->delete();
            $this->permissions->forget('report.'.strtolower($r->uuid));
            $this->audit->record('report.deleted', 'config', null, 'report', $r->id, ['key' => $r->key], $actor->id);
            $r->delete();
        });
    }

    /** @param  array<string, mixed>  $r  a resolved path */
    public function isNumeric(array $r): bool
    {
        if ($r['system'] !== null) {
            return false;
        }
        /** @var FormRuntime $rt */
        $rt = $r['rt'];

        return in_array($rt->type($r['field'])?->storage, self::NUMERIC, true);
    }

    /** @param  array<string, mixed>  $r  a resolved path */
    public function isDate(array $r): bool
    {
        if ($r['system'] !== null) {
            return in_array($r['system'], self::DATE_SYSTEM, true);
        }
        /** @var FormRuntime $rt */
        $rt = $r['rt'];

        return in_array($rt->type($r['field'])?->storage, self::DATES, true);
    }

    /** @return list<string> */
    private static function path(mixed $path): array
    {
        return array_values(array_filter((array) $path, static fn ($s) => is_string($s) && $s !== '' && strlen($s) <= 128));
    }

    /** @return array<string, string> */
    private static function i18n(mixed $values): array
    {
        $out = [];
        foreach ((array) $values as $locale => $v) {
            if (is_string($locale) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $locale) === 1 && is_string($v) && trim($v) !== '') {
                $out[$locale] = mb_substr(trim($v), 0, 255);
            }
        }

        return $out;
    }

    private static function uuid(mixed $v): string
    {
        return is_string($v) && Str::isUuid($v) ? strtolower($v) : (string) Str::uuid7();
    }
}
