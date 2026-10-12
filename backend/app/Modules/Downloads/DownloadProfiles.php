<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Access\AccessCache;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Download profiles (specification §4.23, architecture §10.10): one profile
 * at a time, saved whole with a concurrency hash. A profile of the form is
 * shared and has its own permission `download.{uuid}.use`; a personal
 * profile belongs to the user who made it and may only reach what that user
 * can see. Names, descriptions, column headers and parameter labels are
 * stored per language.
 */
final class DownloadProfiles
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    public const PLACES = ['table_toolbar', 'record_view'];

    public const KINDS = ['field', 'calculated', 'static', 'system'];

    public const TO_MANY = ['flatten', 'aggregate', 'separate_sheet'];

    public const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max', 'first', 'last', 'join'];

    public const OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'starts_with', 'in', 'between', 'empty', 'not_empty'];

    public const PARAMETER_TYPES = ['text', 'number', 'date', 'date_range', 'choice', 'user', 'department'];

    public const FORMAT_TYPES = ['text', 'date', 'datetime', 'number', 'currency', 'percent'];

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly DownloadPaths $paths,
        private readonly Translator $translator,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly ObjectPermissions $permissions,
        private readonly AuditWriter $audit,
        private readonly AccessCache $cache,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Profiles of a form a user may manage: shared ones for profile managers,
     * and their own personal ones.
     *
     * @return list<array<string, mixed>>
     */
    public function list(Form $form, User $user, bool $manager): array
    {
        $rows = DB::table('download_profiles')->where('form_id', $form->id)->whereNull('deleted_at')
            ->where(static fn ($q) => $manager ? $q->where('is_personal', false)->orWhere('owner_user_id', $user->id) : $q->where('owner_user_id', $user->id))
            ->orderBy('is_personal')->orderBy('key')->get();
        $tr = $this->translator->allMany('download_profile', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());

        return $rows->map(fn (object $p) => [
            'uuid' => strtolower((string) $p->uuid), 'key' => $p->key, 'personal' => (bool) $p->is_personal, 'mine' => (int) $p->owner_user_id === $user->id,
            'name' => $this->translator->labelOf($tr[(int) $p->id]['name'] ?? [], (string) $p->key), 'formats' => json_decode((string) $p->formats, true) ?: [],
            'active' => (bool) $p->is_active, 'columns' => DB::table('download_profile_columns')->where('download_profile_id', $p->id)->count(),
            'schedules' => DB::table('download_schedules')->where('download_profile_id', $p->id)->count(),
        ])->values()->all();
    }

    public function row(string $uuid): ?object
    {
        return DB::table('download_profiles')->where('uuid', strtolower($uuid))->whereNull('deleted_at')->first();
    }

    /** @return array<string, mixed> the profile as the builder edits it */
    public function load(object $p): array
    {
        $tr = $this->translator->allMany('download_profile', [(int) $p->id])[(int) $p->id] ?? [];
        $columns = DB::table('download_profile_columns')->where('download_profile_id', $p->id)->orderBy('sort_order')->orderBy('id')->get();
        $ctr = $this->translator->allMany('download_profile_column', $columns->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $parameters = json_decode((string) $p->parameters, true) ?: [];

        return [
            'uuid' => strtolower((string) $p->uuid),
            'key' => $p->key,
            'personal' => (bool) $p->is_personal,
            'i18n' => ['name' => (object) ($tr['name'] ?? []), 'description' => (object) ($tr['description'] ?? [])],
            'formats' => json_decode((string) $p->formats, true) ?: [],
            'sheetMode' => $p->sheet_mode,
            'fileName' => $p->file_name_pattern,
            'xlsx' => json_decode((string) $p->xlsx_options, true) ?: [],
            'csv' => json_decode((string) $p->csv_options, true) ?: [],
            'pdf' => json_decode((string) $p->pdf_options, true) ?: [],
            'applyUserFilters' => (bool) $p->apply_user_filters,
            'allowSelected' => (bool) $p->allow_selected_records,
            'availableIn' => json_decode((string) $p->available_in, true) ?: [],
            'parameters' => array_map(static fn (array $x) => $x + ['i18n' => ['label' => (object) ($tr['parameters.'.$x['key'].'.label'] ?? [])]], $parameters),
            'maxRows' => (int) $p->max_rows,
            'active' => (bool) $p->is_active,
            'columns' => $columns->map(static fn (object $c) => [
                'uuid' => strtolower((string) $c->uuid),
                'kind' => $c->kind,
                'path' => json_decode((string) ($c->path ?? 'null'), true),
                'expression' => json_decode((string) ($c->expression ?? 'null'), true),
                'staticValue' => $c->static_value,
                'toMany' => $c->to_many_mode,
                'aggregate' => $c->aggregate_fn,
                'separator' => $c->join_separator,
                'sheet' => $c->sheet_key,
                'width' => $c->width === null ? null : (int) $c->width,
                'format' => json_decode((string) ($c->format ?? 'null'), true),
                'i18n' => ['header' => (object) ($ctr[(int) $c->id]['header'] ?? [])],
            ])->values()->all(),
            'filters' => DB::table('download_profile_filters')->where('download_profile_id', $p->id)->orderBy('sort_order')->orderBy('id')->get()->map(static fn (object $f) => [
                'uuid' => strtolower((string) $f->uuid),
                'path' => json_decode((string) $f->path, true),
                'operator' => $f->operator,
                // Stored inside an object: SQL Server's ISJSON refuses a bare scalar.
                'value' => $f->value === null ? null : (json_decode((string) $f->value, true)['v'] ?? null),
                'valueExpression' => json_decode((string) ($f->value_expression ?? 'null'), true),
                'parameter' => $f->parameter_key,
            ])->values()->all(),
        ];
    }

    public function hash(object $p): string
    {
        return DefinitionCompiler::hash($this->load($p));
    }

    /**
     * Creates or replaces a profile. A personal profile belongs to `$owner`
     * and may only use paths that user can see.
     *
     * @param  array<string, mixed>  $doc
     */
    public function save(Form $form, ?object $existing, array $doc, User $actor, bool $personal): object
    {
        $rt = $this->runtimes->forForm($form) ?? throw ValidationException::withMessages(['profile' => __('downloads.publish_first')]);
        $owner = $personal ? ($existing === null ? $actor : (User::query()->find($existing->owner_user_id) ?? $actor)) : null;
        $p = $this->check($form, $rt, $existing, $doc, $owner);
        $id = DB::transaction(function () use ($form, $existing, $p, $actor, $owner): int {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $row = [
                'updated_at' => $now, 'updated_by' => $actor->id, 'key' => $p['key'], 'formats' => json_encode($p['formats']),
                'sheet_mode' => $p['sheetMode'], 'file_name_pattern' => $p['fileName'],
                'xlsx_options' => json_encode((object) $p['xlsx']), 'csv_options' => json_encode((object) $p['csv']), 'pdf_options' => json_encode((object) $p['pdf'], JSON_UNESCAPED_UNICODE),
                'apply_user_filters' => $p['applyUserFilters'], 'allow_selected_records' => $p['allowSelected'], 'available_in' => json_encode($p['availableIn']),
                'parameters' => json_encode(array_map(static fn (array $x) => array_diff_key($x, ['i18n' => true]), $p['parameters']), JSON_UNESCAPED_UNICODE),
                'max_rows' => $p['maxRows'], 'is_active' => $p['active'],
            ];
            if ($existing === null) {
                $id = (int) DB::table('download_profiles')->insertGetId($row + [
                    'uuid' => $p['uuid'], 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $actor->id,
                    'form_id' => $form->id, 'is_personal' => $owner !== null, 'owner_user_id' => $owner?->id,
                ]);
            } else {
                $id = (int) $existing->id;
                DB::table('download_profiles')->where('id', $id)->update($row);
            }
            $tr = ['name' => $p['i18n']['name'], 'description' => $p['i18n']['description']];
            foreach ($p['parameters'] as $x) {
                $tr['parameters.'.$x['key'].'.label'] = $x['i18n']['label'];
            }
            $this->translator->forget('download_profile', $id);
            $this->translator->syncObjects('download_profile', [$id => $tr]);
            $this->saveColumns($id, $p['columns'], $now);
            $this->saveFilters($id, $p['filters'], $now);
            if ($owner === null) {
                $this->permissions->registerDownload($id, $p['uuid']);
            }
            $this->audit->record($existing === null ? 'download_profile.created' : 'download_profile.updated', 'config', null, 'download_profile', $id,
                ['key' => $p['key'], 'columns' => count($p['columns']), 'personal' => $owner !== null], $actor->id, null, $form->id);
            $this->cache->bump();

            return $id;
        });

        return DB::table('download_profiles')->where('id', $id)->first();
    }

    /** A copy with a new key, shared or personal as the original. */
    public function duplicate(Form $form, object $p, User $actor, bool $personal): object
    {
        $doc = $this->load($p);
        $doc['uuid'] = (string) Str::uuid();
        $base = substr((string) $p->key, 0, 40).'_copy';
        $key = $base;
        for ($n = 2; DB::table('download_profiles')->where('form_id', $form->id)->where('key', $key)->exists(); $n++) {
            $key = $base.$n;
        }
        $doc['key'] = $key;
        $doc['columns'] = array_map(static fn (array $c) => ['uuid' => (string) Str::uuid()] + $c, $doc['columns']);
        $doc['filters'] = array_map(static fn (array $f) => ['uuid' => (string) Str::uuid()] + $f, $doc['filters']);
        foreach ($doc['i18n']['name'] as $locale => $name) {
            $doc['i18n']['name']->{$locale} = mb_substr($name.' '.__('downloads.copy_suffix'), 0, 255);
        }

        return $this->save($form, null, $doc, $actor, $personal);
    }

    public function delete(object $p, User $actor): void
    {
        DB::transaction(function () use ($p, $actor): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('download_profiles')->where('id', $p->id)->update(['deleted_at' => $now, 'deleted_by' => $actor->id, 'is_active' => false]);
            foreach (DB::table('download_schedules')->where('download_profile_id', $p->id)->get(['id', 'scheduled_task_id']) as $s) {
                DB::table('download_schedules')->where('id', $s->id)->update(['is_active' => false, 'updated_at' => $now]);
                if ($s->scheduled_task_id !== null) {
                    DB::table('scheduled_tasks')->where('id', $s->scheduled_task_id)->update(['is_enabled' => false, 'updated_at' => $now]);
                }
            }
            $this->permissions->forget('download.'.strtolower((string) $p->uuid));
            $this->audit->record('download_profile.deleted', 'config', null, 'download_profile', (int) $p->id, ['key' => $p->key], $actor->id, null, (int) $p->form_id);
        });
    }

    /**
     * Checks a profile; returns it normalized (as load() returns it), or
     * throws with every problem. Also used for the live preview of an
     * unsaved profile.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public function check(Form $form, FormRuntime $rt, ?object $existing, array $d, ?User $owner, bool $preview = false): array
    {
        $errors = [];
        $default = $this->translator->defaultLocale();
        $uuid = $existing === null ? strtolower((string) ($d['uuid'] ?? '')) : strtolower((string) $existing->uuid);
        if (! Str::isUuid($uuid) || ($existing === null && DB::table('download_profiles')->where('uuid', $uuid)->exists())) {
            $uuid = (string) Str::uuid();
        }
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key) !== 1) {
            $errors['key'][] = __('downloads.bad_key');
        } elseif (! $preview && DB::table('download_profiles')->where('form_id', $form->id)->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('downloads.key_taken');
        }
        $name = $this->texts($d['i18n']['name'] ?? [], 255);
        if (($name[$default] ?? '') === '') {
            $errors["i18n.name.{$default}"][] = __('downloads.name_required');
        }
        $description = $this->texts($d['i18n']['description'] ?? [], 1000);
        $formats = array_values(array_unique(array_filter((array) ($d['formats'] ?? []), 'is_string')));
        if ($formats === [] || array_diff($formats, self::FORMATS) !== []) {
            $errors['formats'][] = __('downloads.bad_formats');
        }
        $places = array_values(array_unique(array_filter((array) ($d['availableIn'] ?? []), 'is_string')));
        if ($places === [] || array_diff($places, self::PLACES) !== []) {
            $errors['availableIn'][] = __('downloads.bad_places');
        }
        $fileName = trim((string) ($d['fileName'] ?? ''));
        if ($fileName === '' || mb_strlen($fileName) > 255 || preg_match('/[\/\\\\:*?"<>|]/', (string) preg_replace('/\{[^}]*\}/', '', $fileName)) === 1) {
            $errors['fileName'][] = __('downloads.bad_file_name');
        } else {
            foreach ($this->placeholders($fileName) as $ph) {
                if (! in_array($ph, ['form', 'profile', 'user', 'date'], true) && ! str_starts_with($ph, 'date:') && ! str_starts_with($ph, 'param.')) {
                    $errors['fileName'][] = __('downloads.unknown_placeholder', ['name' => $ph]);
                }
            }
        }
        $max = (int) $this->settings->get('downloads', 'max_rows');
        $maxRows = $d['maxRows'] ?? 0;
        if (! is_int($maxRows) || $maxRows < 0 || $maxRows > $max) {
            $errors['maxRows'][] = __('downloads.bad_number', ['min' => 0, 'max' => $max]);
        }

        // Parameters the user fills at download time.
        $parameters = [];
        $paramKeys = [];
        foreach (array_values((array) ($d['parameters'] ?? [])) as $i => $x) {
            $p = "parameters.{$i}";
            $k = (string) ($x['key'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $k) !== 1 || isset($paramKeys[$k])) {
                $errors["{$p}.key"][] = __('downloads.bad_key');
            }
            $paramKeys[$k] = $x['type'] ?? null;
            if (! in_array($x['type'] ?? null, self::PARAMETER_TYPES, true)) {
                $errors["{$p}.type"][] = __('validation.in', ['attribute' => 'type']);
            }
            $label = $this->texts($x['i18n']['label'] ?? [], 120);
            if (($label[$default] ?? '') === '') {
                $errors["{$p}.i18n.label.{$default}"][] = __('downloads.label_required');
            }
            $options = [];
            if (($x['type'] ?? null) === 'choice') {
                foreach ((array) ($x['options'] ?? []) as $o) {
                    if (is_array($o) && is_string($o['value'] ?? null) && $o['value'] !== '') {
                        $options[] = ['value' => mb_substr($o['value'], 0, 120), 'label' => $this->texts($o['label'] ?? [], 120)];
                    }
                }
                if ($options === []) {
                    $errors["{$p}.options"][] = __('downloads.options_required');
                }
            }
            $parameters[] = ['key' => $k, 'type' => $x['type'] ?? 'text', 'required' => (bool) ($x['required'] ?? false), 'default' => is_scalar($x['default'] ?? null) ? $x['default'] : null,
                'options' => $options, 'i18n' => ['label' => $label]];
        }
        if (count($parameters) > 20) {
            $errors['parameters'][] = __('downloads.too_many', ['max' => 20]);
        }

        $resolver = $this->draftValidator->resolver($this->drafts->normalize($this->drafts->load($form)));
        $expr = function (mixed $ast, string $path, ?string $type) use ($resolver, &$errors): ?array {
            if (! is_array($ast)) {
                $errors[$path][] = __('downloads.expression_required');

                return null;
            }
            try {
                TypeChecker::check($ast, $resolver, $type);
            } catch (StaticError $e) {
                $errors[$path][] = $e->getMessage();
            }

            return $ast;
        };

        // Columns.
        $columns = [];
        $seen = [];
        $flattenBranch = null;
        $sheets = [];
        foreach (array_values((array) ($d['columns'] ?? [])) as $i => $c) {
            $p = "columns.{$i}";
            $cu = strtolower((string) ($c['uuid'] ?? ''));
            if (! Str::isUuid($cu) || isset($seen[$cu])) {
                $cu = (string) Str::uuid();
            }
            $seen[$cu] = true;
            $kind = $c['kind'] ?? null;
            $col = ['uuid' => $cu, 'kind' => $kind, 'path' => null, 'expression' => null, 'staticValue' => null, 'toMany' => null, 'aggregate' => null,
                'separator' => null, 'sheet' => null, 'width' => null, 'format' => null, 'i18n' => ['header' => $this->texts($c['i18n']['header'] ?? [], 255)]];
            if (! in_array($kind, self::KINDS, true)) {
                $errors["{$p}.kind"][] = __('validation.in', ['attribute' => 'kind']);

                continue;
            }
            if ($kind === 'field' || $kind === 'system') {
                $path = array_values(array_filter((array) ($c['path'] ?? []), 'is_string'));
                $r = $path === [] ? null : $this->paths->resolve($rt, $path, $owner);
                if ($r === null || ($kind === 'system') !== ($r['system'] !== null)) {
                    $errors["{$p}.path"][] = __('downloads.bad_path');

                    continue;
                }
                $col['path'] = $path;
                if ($r['toMany']) {
                    $mode = $c['toMany'] ?? null;
                    if (! in_array($mode, self::TO_MANY, true)) {
                        $errors["{$p}.toMany"][] = __('downloads.to_many_required');
                    }
                    $col['toMany'] = $mode;
                    $branch = array_slice($path, 0, (int) $r['branch'] + 1);
                    if ($mode === 'aggregate') {
                        $fn = $c['aggregate'] ?? null;
                        if (! in_array($fn, self::AGGREGATES, true)) {
                            $errors["{$p}.aggregate"][] = __('validation.in', ['attribute' => 'aggregate']);
                        }
                        $col['aggregate'] = $fn;
                        $col['separator'] = $fn === 'join' ? mb_substr((string) ($c['separator'] ?? ', '), 0, 16) : null;
                    } elseif ($mode === 'flatten') {
                        // Rows repeat for one linked form at a time.
                        $flattenBranch ??= $branch;
                        if ($branch !== $flattenBranch) {
                            $errors["{$p}.toMany"][] = __('downloads.one_flatten_branch');
                        }
                    } elseif ($mode === 'separate_sheet') {
                        $sheet = (string) ($c['sheet'] ?? '');
                        if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $sheet) !== 1) {
                            $errors["{$p}.sheet"][] = __('downloads.bad_sheet');
                        } elseif (isset($sheets[$sheet]) && $sheets[$sheet] !== $branch) {
                            $errors["{$p}.sheet"][] = __('downloads.sheet_branch');
                        }
                        $sheets[$sheet] ??= $branch;
                        $col['sheet'] = $sheet;
                    }
                }
            } elseif ($kind === 'calculated') {
                if ($owner !== null) {
                    // A personal profile reads only fields: formulas are for shared profiles, checked per user when downloaded.
                    $errors["{$p}.kind"][] = __('downloads.personal_no_formulas');

                    continue;
                }
                $col['expression'] = $expr($c['expression'] ?? null, "{$p}.expression", null);
                if (($col['i18n']['header'][$default] ?? '') === '') {
                    $errors["{$p}.i18n.header.{$default}"][] = __('downloads.header_required');
                }
            } else {
                $col['staticValue'] = mb_substr((string) ($c['staticValue'] ?? ''), 0, 1024);
                if (($col['i18n']['header'][$default] ?? '') === '') {
                    $errors["{$p}.i18n.header.{$default}"][] = __('downloads.header_required');
                }
            }
            $width = $c['width'] ?? null;
            if ($width !== null && (! is_int($width) || $width < 4 || $width > 120)) {
                $errors["{$p}.width"][] = __('downloads.bad_number', ['min' => 4, 'max' => 120]);
            }
            $col['width'] = $width;
            $col['format'] = $this->format($c['format'] ?? null, "{$p}.format", $errors);
            $columns[] = $col;
        }
        if ($columns === []) {
            $errors['columns'][] = __('downloads.columns_required');
        } elseif (count($columns) > 200) {
            $errors['columns'][] = __('downloads.too_many', ['max' => 200]);
        }
        $sheetMode = $sheets === [] ? 'single' : 'per_related_form';

        // Fixed filters, each with a value, an expression or a parameter.
        $filters = [];
        foreach (array_values((array) ($d['filters'] ?? [])) as $i => $f) {
            $p = "filters.{$i}";
            $fu = strtolower((string) ($f['uuid'] ?? ''));
            if (! Str::isUuid($fu)) {
                $fu = (string) Str::uuid();
            }
            $path = array_values(array_filter((array) ($f['path'] ?? []), 'is_string'));
            $r = $path === [] ? null : $this->paths->resolve($rt, $path, $owner);
            $forward = $r !== null && $r['field'] !== null && $r['repeater'] === null && array_filter($r['hops'], static fn ($h) => ! in_array($h['kind'], ['ref', 'multi'], true)) === [];
            if (! $forward) {
                $errors["{$p}.path"][] = __('downloads.bad_filter_path');
            }
            $op = $f['operator'] ?? null;
            if (! in_array($op, self::OPERATORS, true)) {
                $errors["{$p}.operator"][] = __('validation.in', ['attribute' => 'operator']);
            }
            $parameter = isset($f['parameter']) && $f['parameter'] !== '' ? (string) $f['parameter'] : null;
            $valueExpression = $f['valueExpression'] ?? null;
            $value = $f['value'] ?? null;
            if (in_array($op, ['empty', 'not_empty'], true)) {
                $value = $valueExpression = $parameter = null;
            } elseif ($parameter !== null) {
                if (! array_key_exists($parameter, $paramKeys)) {
                    $errors["{$p}.parameter"][] = __('downloads.unknown_parameter');
                }
                $value = $valueExpression = null;
            } elseif ($valueExpression !== null) {
                if ($owner !== null) {
                    $errors["{$p}.valueExpression"][] = __('downloads.personal_no_formulas');
                }
                $valueExpression = $expr($valueExpression, "{$p}.valueExpression", null);
                $value = null;
            } elseif ($value === null || $value === '' || $value === []) {
                $errors["{$p}.value"][] = __('downloads.value_required');
            } elseif (! is_scalar($value) && ! (is_array($value) && array_is_list($value) && count($value) <= 200)) {
                $errors["{$p}.value"][] = __('downloads.value_required');
            }
            $filters[] = ['uuid' => $fu, 'path' => $path, 'operator' => $op, 'value' => $value, 'valueExpression' => $valueExpression, 'parameter' => $parameter];
        }
        if (count($filters) > 50) {
            $errors['filters'][] = __('downloads.too_many', ['max' => 50]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $orientation = ($d['pdf']['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';

        return [
            'uuid' => $uuid, 'key' => $key, 'i18n' => ['name' => $name, 'description' => $description],
            'formats' => $formats, 'sheetMode' => $sheetMode, 'fileName' => $fileName,
            'xlsx' => [
                'styledHeader' => (bool) ($d['xlsx']['styledHeader'] ?? true), 'freezeHeader' => (bool) ($d['xlsx']['freezeHeader'] ?? true),
                'titleRow' => (bool) ($d['xlsx']['titleRow'] ?? false), 'logo' => (bool) ($d['xlsx']['logo'] ?? false),
            ],
            'csv' => ['delimiter' => in_array($d['csv']['delimiter'] ?? ',', [',', ';', "\t"], true) ? ($d['csv']['delimiter'] ?? ',') : ',', 'bom' => (bool) ($d['csv']['bom'] ?? true)],
            'pdf' => [
                'orientation' => $orientation, 'pageNumbers' => (bool) ($d['pdf']['pageNumbers'] ?? true),
                'header' => $this->texts($d['pdf']['header'] ?? [], 255), 'footer' => $this->texts($d['pdf']['footer'] ?? [], 255),
            ],
            'applyUserFilters' => (bool) ($d['applyUserFilters'] ?? false), 'allowSelected' => (bool) ($d['allowSelected'] ?? false),
            'availableIn' => $places, 'parameters' => $parameters, 'maxRows' => $maxRows, 'active' => (bool) ($d['active'] ?? true),
            'columns' => $columns, 'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array<string, mixed>|null
     */
    private function format(mixed $f, string $path, array &$errors): ?array
    {
        if ($f === null) {
            return null;
        }
        if (! is_array($f) || ! in_array($f['type'] ?? null, self::FORMAT_TYPES, true)) {
            $errors["{$path}.type"][] = __('validation.in', ['attribute' => 'type']);

            return null;
        }
        $decimals = $f['decimals'] ?? null;
        if ($decimals !== null && (! is_int($decimals) || $decimals < 0 || $decimals > 6)) {
            $errors["{$path}.decimals"][] = __('downloads.bad_number', ['min' => 0, 'max' => 6]);
        }
        $currency = $f['currency'] ?? null;
        if ($f['type'] === 'currency' && (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1)) {
            $errors["{$path}.currency"][] = __('downloads.bad_currency');
        }

        return [
            'type' => $f['type'], 'decimals' => $decimals, 'currency' => $f['type'] === 'currency' ? $currency : null,
            'digits' => ($f['digits'] ?? 'latin') === 'arabic' ? 'arabic' : 'latin',
        ];
    }

    /** @return list<string> */
    public function placeholders(string $pattern): array
    {
        preg_match_all('/\{([^}]+)\}/', $pattern, $m);

        return array_map('trim', $m[1]);
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function saveColumns(int $profileId, array $columns, string $now): void
    {
        $kept = [];
        $tr = [];
        foreach ($columns as $i => $c) {
            $row = [
                'updated_at' => $now, 'sort_order' => $i, 'kind' => $c['kind'],
                'path' => $c['path'] === null ? null : json_encode($c['path'], JSON_UNESCAPED_UNICODE),
                'path_hash' => $c['path'] === null ? null : hash('sha256', (string) json_encode($c['path'])),
                // The record number is the record's identifier column in the schema.
                'system_column' => $c['kind'] === 'system' ? str_replace('record_number', 'record_id', ltrim((string) end($c['path']), '@')) : null,
                'expression' => $c['expression'] === null ? null : json_encode($c['expression'], JSON_UNESCAPED_UNICODE),
                'static_value' => $c['staticValue'], 'to_many_mode' => $c['toMany'], 'aggregate_fn' => $c['aggregate'],
                'join_separator' => $c['separator'], 'sheet_key' => $c['sheet'], 'width' => $c['width'],
                'format' => $c['format'] === null ? null : json_encode($c['format']),
            ];
            $existing = DB::table('download_profile_columns')->where('download_profile_id', $profileId)->where('uuid', $c['uuid'])->value('id');
            if ($existing === null) {
                $id = (int) DB::table('download_profile_columns')->insertGetId($row + ['uuid' => $c['uuid'], 'created_at' => $now, 'download_profile_id' => $profileId]);
            } else {
                $id = (int) $existing;
                DB::table('download_profile_columns')->where('id', $id)->update($row);
            }
            $this->translator->forget('download_profile_column', $id);
            $tr[$id] = ['header' => $c['i18n']['header']];
            $kept[] = $id;
        }
        $this->translator->syncObjects('download_profile_column', $tr);
        foreach (DB::table('download_profile_columns')->where('download_profile_id', $profileId)->whereNotIn('id', $kept ?: [0])->pluck('id') as $gone) {
            $this->translator->forget('download_profile_column', (int) $gone);
            DB::table('download_profile_columns')->where('id', $gone)->delete();
        }
    }

    /** @param  list<array<string, mixed>>  $filters */
    private function saveFilters(int $profileId, array $filters, string $now): void
    {
        DB::table('download_profile_filters')->where('download_profile_id', $profileId)->delete();
        foreach ($filters as $i => $f) {
            DB::table('download_profile_filters')->insert([
                'uuid' => $f['uuid'], 'created_at' => $now, 'updated_at' => $now, 'download_profile_id' => $profileId, 'sort_order' => $i,
                'path' => json_encode($f['path'], JSON_UNESCAPED_UNICODE), 'operator' => $f['operator'],
                'value' => $f['value'] === null ? null : json_encode(['v' => $f['value']], JSON_UNESCAPED_UNICODE),
                'value_expression' => $f['valueExpression'] === null ? null : json_encode($f['valueExpression'], JSON_UNESCAPED_UNICODE),
                'parameter_key' => $f['parameter'],
            ]);
        }
    }

    /** @return array<string, string> */
    private function texts(mixed $map, int $max): array
    {
        $out = [];
        foreach ((array) $map as $locale => $text) {
            if (is_string($text) && trim($text) !== '' && in_array($locale, $this->translator->enabledLocales(), true)) {
                $out[(string) $locale] = mb_substr(trim($text), 0, $max);
            }
        }

        return $out;
    }
}
