<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Expressions\Text\Unicode;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\ClientDefinition;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\JustificationPresenter;
use App\Modules\Records\Runtime\ExpressionContext;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordQuery;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RecordValidator;
use App\Modules\Records\Runtime\References;
use App\Modules\Views\PrintLayouts;
use App\Modules\Views\ViewRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Records runtime API (architecture §21.2 "Records (runtime)"): the client
 * definition for a mode, the record list with search, sort and simple
 * filters, read/create/update/delete/restore through the record pipeline,
 * option lists for choice and lookup fields, server validation of one field,
 * and the record history.
 */
final class RecordController extends Controller
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordPipeline $pipeline,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly AccessResolver $access,
        private readonly RecordScope $scope,
    ) {}

    public function definition(Request $request, Form $form, ClientDefinition $client): JsonResponse
    {
        $data = $request->validate(['mode' => ['sometimes', Rule::in(['create', 'edit', 'view', 'print'])], 'record' => ['sometimes', 'nullable', 'uuid']]);
        $rt = $this->runtime($form, 'view');
        $mode = $data['mode'] ?? 'view';
        // Field access depends on the status of the record being viewed or edited.
        $status = null;
        if (($data['record'] ?? null) !== null) {
            $row = DB::table($rt->table)->where('uuid', strtolower($data['record']))->first(['id', 'status_id']);
            abort_if($row === null || ! $this->scope->allows($rt, $this->user(), 'view', (int) $row->id), 404, __('records.not_found'));
            $status = $row->status_id === null ? null : (int) $row->status_id;
        }
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, $mode, $status);
        abort_unless($levels['modes'][$mode] ?? false, 403, __('records.forbidden'));

        // Print layouts the user can choose from (names only; printing checks the print permission).
        $layouts = ($levels['modes']['print'] ?? false) ? array_map(static fn (array $l) => ['key' => $l['key'], 'name' => app(Translator::class)->labelOf($l['i18n']['name'] ?? null, $l['key']), 'default' => $l['default']], app(PrintLayouts::class)->load($form)) : [];

        return response()->json(['data' => $client->build($rt->definition, $levels, $mode) + ['name' => $form->translate('name') ?? Translator::humanize((string) $form->key), 'names' => $form->translationsFor('name'), 'user' => app(ExpressionContext::class)->client($this->user()), 'print_layouts' => $layouts]]);
    }

    public function index(Request $request, Form $form, RecordQuery $query): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        $data = $request->validate(RecordQuery::rules() + [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'view' => ['sometimes', 'nullable', 'uuid'],
            'vf' => ['sometimes', 'array', 'max:30'],
        ]);
        $user = $this->user();
        $levels = $this->fieldAccess->resolve($user, $form->id, $form->uuid, $rt->definition, 'view');
        $q = $query->build($rt, $user, $levels['fields'], $data);
        $views = app(ViewRuntime::class);
        $view = $views->pick($rt, $user, isset($data['view']) ? strtolower($data['view']) : null);
        $presented = $view === null ? null : $views->present($rt, $user, $view);
        if ($view !== null) {
            $views->filter($q, $rt, $user, $view, $data['vf'] ?? []);
        }
        $total = (clone $q)->count();
        $perPage = $data['per_page'] ?? ($presented['page_size'] ?? 25);
        $page = $data['page'] ?? 1;
        $totals = $presented === null ? [] : $views->totals($q, $rt, $user, $presented);
        if ($view !== null) {
            $sortPath = isset($data['sort']) ? explode('.', (string) $data['sort']) : null;
            $views->order($q, $rt, $user, $view, $sortPath === null ? null : ['path' => $sortPath, 'dir' => $data['direction'] ?? 'asc']);
        } else {
            $query->order($q, $rt, $levels['fields'], $data);
        }
        $rows = $q->forPage($page, $perPage)->get()->map(static fn ($r) => (array) $r)->all();
        $records = $this->store->hydrate($rt, $rows, false);
        $out = $this->presenter->many($rt, $records, $levels['fields']);
        if ($presented !== null) {
            $linked = $views->linkedValues($rt, $user, $presented, array_map(static fn ($r) => $r['id'], $records));
            foreach ($out as $i => &$row) {
                $row['linked'] = $linked[$records[$i]['id']] ?? (object) [];
            }
            unset($row);
        }

        return response()->json([
            'data' => $out,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'view' => $presented, 'totals' => $totals === [] ? (object) [] : $totals,
                'views' => array_map(static fn ($v) => ['uuid' => $v['uuid'], 'key' => $v['key'], 'name' => $v['i18n']['name']], $views->available($rt, $user))],
        ]);
    }

    public function show(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        $found = $this->store->find($rt, strtolower($record), $this->access->allows($this->user(), "form.{$form->uuid}.restore"));
        abort_if($found === null || ! $this->scope->allows($rt, $this->user(), 'view', $found['id']), 404, __('records.not_found'));
        $status = $found['system']['status_id'] ?? null;
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'edit', $status);
        $viewLevels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view', $status);
        $mayEdit = $this->scope->allows($rt, $this->user(), 'edit', $found['id']);
        $mayDelete = $this->scope->allows($rt, $this->user(), 'delete', $found['id']);
        $merged = [];
        foreach ($viewLevels['fields'] as $uuid => $l) {
            $merged[$uuid] = $l === 'hidden' && ($levels['fields'][$uuid] ?? 'hidden') === 'hidden' ? 'hidden' : $l;
        }

        return response()->json(['data' => $this->presenter->present($rt, $found, $merged) + ['permissions' => [
            'edit' => $levels['modes']['edit'] && $mayEdit && $found['system']['deleted_at'] === null,
            'delete' => $levels['modes']['delete'] && $mayDelete && $found['system']['deleted_at'] === null,
            'restore' => $found['system']['deleted_at'] !== null && $mayDelete && $this->access->allows($this->user(), "form.{$form->uuid}.restore"),
            'print' => $levels['modes']['print'],
            'view_log' => $this->access->allows($this->user(), "form.{$form->uuid}.view_log"),
        ]]]);
    }

    public function store(Request $request, Form $form): JsonResponse
    {
        $rt = $this->runtime($form, 'create');
        $data = $request->validate(['values' => ['present', 'array'], 'params' => ['sometimes', 'array', 'max:20'], 'params.*' => ['nullable', 'string', 'max:500']]);

        return $this->run(function () use ($rt, $data, $request, $form): JsonResponse {
            $result = $this->pipeline->create($rt, $this->user(), $data['values'], $data['params'] ?? [], $this->key($request));
            $record = $this->store->findById($rt, $result['id']);
            $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view');

            return response()->json(['data' => $this->presenter->present($rt, $record, $levels['fields'])], 201);
        });
    }

    public function update(Request $request, Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'edit');
        $data = $request->validate(['values' => ['present', 'array'], 'row_version' => ['required_without:If-Match', 'nullable', 'integer', 'min:1']] + self::justificationRules());
        $expected = (int) ($data['row_version'] ?? $request->header('If-Match'));
        abort_if($expected < 1, 428, __('records.row_version_required'));

        return $this->run(function () use ($rt, $data, $request, $form, $record, $expected): JsonResponse {
            $result = $this->pipeline->update($rt, $this->user(), strtolower($record), $expected, $data['values'], $this->key($request), 'ui', $data['justification'] ?? null);
            $found = $this->store->findById($rt, $result['id']);
            $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view');

            return response()->json(['data' => $this->presenter->present($rt, $found, $levels['fields']) + ['changed' => $result['changed']]]);
        });
    }

    public function destroy(Request $request, Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'delete');
        $expected = (int) ($request->input('row_version') ?? $request->header('If-Match'));
        abort_if($expected < 1, 428, __('records.row_version_required'));
        $data = $request->validate(self::justificationRules());

        return $this->run(function () use ($rt, $request, $record, $expected, $data): JsonResponse {
            $this->pipeline->delete($rt, $this->user(), strtolower($record), $expected, $this->key($request), $data['justification'] ?? null);

            return response()->json(null, 204);
        });
    }

    public function restore(Request $request, Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'restore');
        $data = $request->validate(self::justificationRules());

        return $this->run(function () use ($rt, $request, $record, $data): JsonResponse {
            $this->pipeline->restore($rt, $this->user(), strtolower($record), $this->key($request), $data['justification'] ?? null);

            return response()->json(null, 204);
        });
    }

    /**
     * Options of a choice or lookup field: static options, collection or form
     * records (searchable, paged, cascading by a parent value), users, roles or
     * departments. Only fields the user can see are served.
     */
    public function options(Request $request, Form $form, string $field, References $refs): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        $data = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:100'], 'depends' => ['sometimes', 'nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1'], 'uuids' => ['sometimes', 'array', 'max:100'], 'uuids.*' => ['uuid']]);
        $f = $this->fieldByKey($rt, $field);
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'create');
        $viewLevels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'edit');
        abort_if(($levels['fields'][$f['uuid']] ?? 'hidden') === 'hidden' && ($viewLevels['fields'][$f['uuid']] ?? 'hidden') === 'hidden', 404);
        $opts = $f['options'] ?? [];
        $term = trim((string) ($data['q'] ?? ''));
        $pageSize = (int) ($opts['pageSize'] ?? 50);
        $page = $data['page'] ?? 1;
        $locale = app()->getLocale();
        $storage = $rt->type($f)?->storage;

        if (($opts['source'] ?? null) === 'static' && $rt->targetTable($f) === null) {
            $items = array_values(array_filter($opts['static'] ?? [], static fn ($o) => $o['active'] ?? true));
            if (($data['depends'] ?? null) !== null) {
                $items = array_values(array_filter($items, static fn ($o) => ($o['parent'] ?? null) === null || $o['parent'] === $data['depends']));
            }
            $out = array_map(static fn ($o) => ['value' => $o['value'], 'label' => $o['i18n']['label'][$locale] ?? ($o['i18n']['label']['en'] ?? $o['value']), 'group' => $o['group'] ?? null, 'color' => $o['color'] ?? null, 'icon' => $o['icon'] ?? null, 'parent' => $o['parent'] ?? null, 'uuid' => $o['uuid']], $items);
            if ($term !== '') {
                $needle = mb_strtolower($term);
                $out = array_values(array_filter($out, static fn ($o) => str_contains(mb_strtolower($o['label']), $needle) || str_contains(mb_strtolower($o['value']), $needle)));
            }

            return response()->json(['data' => $out, 'meta' => ['total' => count($out)]]);
        }

        $table = $rt->targetTable($f);
        abort_if($table === null, 404);
        if (in_array($storage, ['user', 'role', 'department'], true)) {
            $q = DB::table($table)->select(['id', 'uuid']);
            if ($table !== 'roles') {
                $q->whereNull('deleted_at');
            }
            if ($table === 'users') {
                $q->addSelect('name')->where('status', 'active');
                if ($term !== '') {
                    $driver = app(DatabaseDriver::class);
                    $q->where(static fn ($w) => $w->where(static fn ($x) => $driver->caseInsensitiveLike($x, 'name', $term))->orWhere(static fn ($x) => $driver->caseInsensitiveLike($x, 'email', $term)));
                }
            }
            if (! empty($data['uuids'])) {
                $q->whereIn('uuid', $data['uuids']);
            }
            $rows = $q->orderBy('id')->limit(500)->get();
            $titles = $refs->titles($rt, $f, $rows->pluck('uuid')->map(fn ($u) => strtolower((string) $u))->all());
            $items = $rows->map(static fn ($r) => ['value' => strtolower((string) $r->uuid), 'label' => $titles[strtolower((string) $r->uuid)] ?? __('records.untitled')])->values()->all();
            if ($term !== '' && $table !== 'users') {
                $needle = mb_strtolower($term);
                $items = array_values(array_filter($items, static fn ($o) => str_contains(mb_strtolower($o['label']), $needle)));
            }

            return response()->json(['data' => array_slice($items, ($page - 1) * $pageSize, $pageSize), 'meta' => ['total' => count($items)]]);
        }

        // Collection or form records.
        $target = $this->runtimes->forUuid((string) $rt->targetFormUuid($f));
        abort_if($target === null, 404);
        abort_unless($this->access->allows($this->user(), "form.{$target->form->uuid}.view") || $target->form->kind === 'collection', 403, __('records.forbidden'));
        $display = $refs->displayColumn($rt, $f);
        $q = DB::table($target->table)->whereNull('deleted_at');
        $this->scope->apply($q, $target, $this->user(), 'view');
        if ($term !== '') {
            $display !== null
                ? app(DatabaseDriver::class)->caseInsensitiveLike($q, $display, $term)
                : app(DatabaseDriver::class)->caseInsensitiveLike($q, 'search_text', mb_strtolower(Unicode::normalizeArabic($term)));
        }
        if (! empty($data['uuids'])) {
            $q->whereIn('uuid', $data['uuids']);
        }
        if (($data['depends'] ?? null) !== null && ($path = $opts['dependsPath'] ?? null) !== null) {
            $depField = $target->keys[$path[0]] ?? null;
            $depCol = $depField === null ? null : $target->column($depField);
            if ($depCol !== null) {
                $dep = $data['depends'];
                if ($depCol['type'] === 'bigint' && preg_match('/^[0-9a-f-]{36}$/i', $dep) === 1) {
                    $dep = $refs->ids((string) $target->targetTable($target->fields[$depField]), [strtolower($dep)])[strtolower($dep)] ?? 0;
                }
                $q->where($depCol['name'], $dep);
            }
        }
        $total = (clone $q)->count();
        $rows = $q->orderBy($display ?? 'id')->forPage($page, $pageSize)->get();
        $records = $this->store->hydrate($target, $rows->map(static fn ($r) => (array) $r)->all(), false);
        $previewKeys = array_map(static fn ($p) => $p[0], $opts['preview'] ?? []);
        // The preview card names each value by its field's label, never by its key.
        $translator = app(Translator::class);
        $previewLabels = [];
        foreach ($previewKeys as $k) {
            $pf = $target->fields[$target->keys[$k] ?? ''] ?? null;
            $previewLabels[$k] = $translator->labelOf($pf['i18n']['label'] ?? null, (string) $k);
        }

        return response()->json(['data' => array_map(static function ($r, $raw) use ($display, $previewKeys, $previewLabels) {
            return [
                'value' => $r['uuid'],
                'label' => (string) ($display !== null ? ($raw->{$display} ?? __('records.untitled')) : ($r['system']['record_number'] ?? __('records.untitled'))),
                'preview' => array_intersect_key($r['values'], array_flip($previewKeys)),
                'previewLabels' => $previewLabels,
            ];
        }, $records, $rows->all()), 'meta' => ['total' => $total]]);
    }

    /** Server check of one field's async rule (exists in a collection) while the user types. */
    public function validateField(Request $request, Form $form, RecordValidator $validator): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        $data = $request->validate(['field' => ['required', 'string', 'max:48'], 'value' => ['present', 'nullable', 'string', 'max:500']]);
        $f = $this->fieldByKey($rt, $data['field']);
        $async = $f['validation']['async'] ?? null;
        if ($async === null || $data['value'] === null || $data['value'] === '') {
            return response()->json(['data' => ['valid' => true]]);
        }
        $exists = $validator->existsIn($async['collection'], $async['path'], $data['value']);

        return response()->json(['data' => ['valid' => $exists === null || $exists === ($async['type'] === 'exists_in')]]);
    }

    /** Record history from the audit log (needs View Log on the form). */
    public function history(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.view_log"), 403, __('records.forbidden'));
        $found = $this->store->find($rt, strtolower($record), true);
        abort_if($found === null || ! $this->scope->allows($rt, $this->user(), 'view', $found['id']), 404);
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view', $found['system']['status_id'] ?? null);
        $seeJustifications = $this->access->allows($this->user(), 'system.view_justifications');
        $hiddenKeys = [];
        foreach ($levels['fields'] as $uuid => $l) {
            if ($l === 'hidden') {
                $hiddenKeys[$rt->fields[$uuid]['key']] = true;
            }
        }
        $entries = DB::table('audit_logs')->where('form_id', $form->id)->where('record_id', $found['id'])->orderByDesc('id')->limit(200)->get();
        $users = DB::table('users')->whereIn('id', [...$entries->pluck('actor_user_id')->filter(), ...$entries->pluck('on_behalf_of_user_id')->filter()])->pluck('name', 'id');
        $justifications = $seeJustifications ? app(JustificationPresenter::class)->many($entries->pluck('justification_id')->filter()->map(static fn ($v) => (int) $v)->all()) : [];

        return response()->json(['data' => $entries->map(static fn ($e) => [
            'event' => $e->event,
            'at' => Carbon::parse($e->occurred_at, 'UTC')->toIso8601ZuluString(),
            'by' => $users[$e->actor_user_id] ?? null,
            'on_behalf_of' => $e->on_behalf_of_user_id === null ? null : ($users[$e->on_behalf_of_user_id] ?? null),
            'changes' => array_values(array_filter(json_decode((string) $e->changes, true) ?: [], static fn ($c) => ! isset($hiddenKeys[$c['field_key']]))),
            'justification' => $e->justification_id === null ? null : ($justifications[(int) $e->justification_id] ?? ['restricted' => true]),
        ])->values()]);
    }

    /** @return array<string, list<mixed>> */
    public static function justificationRules(): array
    {
        return [
            'justification' => ['sometimes', 'nullable', 'array'],
            'justification.reason_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'justification.reason_code' => ['sometimes', 'nullable', 'uuid'],
            'justification.note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'justification.attachments' => ['sometimes', 'array', 'max:20'],
            'justification.attachments.*' => ['uuid'],
        ];
    }

    private function run(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }
    }

    private function runtime(Form $form, string $ability): FormRuntime
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null || ! in_array($form->state, ['published', 'schema_inconsistent'], true), 404, __('records.form_unavailable'));
        // Delegates hold their delegators' access to the delegated forms (architecture §19.4).
        abort_if($this->pipeline->permitted($rt, $this->user(), ["form.{$form->uuid}.view"]) === false, 404, __('records.form_unavailable'));
        if ($ability !== 'view') {
            abort_if($this->pipeline->permitted($rt, $this->user(), ["form.{$form->uuid}.{$ability}"]) === false, 403, __('records.forbidden'));
        }

        return $rt;
    }

    /** @return array<string, mixed> */
    private function fieldByKey(FormRuntime $rt, string $key): array
    {
        $uuid = $rt->keys[$key] ?? null;
        if ($uuid === null) {
            foreach ($rt->repeaters as $rep) {
                $uuid ??= $rep['fields'][$key] ?? null;
            }
        }
        abort_if($uuid === null, 404);

        return $rt->fields[$uuid];
    }

    private function key(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key', '');

        return preg_match('/^[A-Za-z0-9-]{16,64}$/', $key) === 1 ? $key : (string) Str::uuid7();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
