<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Expressions\Text\Unicode;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Forms\Definition\ClientDefinition;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RecordValidator;
use App\Modules\Records\Runtime\References;
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
    ) {}

    public function definition(Request $request, Form $form, ClientDefinition $client): JsonResponse
    {
        $data = $request->validate(['mode' => ['sometimes', Rule::in(['create', 'edit', 'view', 'print'])]]);
        $rt = $this->runtime($form, 'view');
        $mode = $data['mode'] ?? 'view';
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, $mode);
        abort_unless($levels['modes'][$mode] ?? false, 403, __('records.forbidden'));

        return response()->json(['data' => $client->build($rt->definition, $levels, $mode) + ['name' => $form->translate('name') ?? $form->key, 'names' => $form->translationsFor('name')]]);
    }

    public function index(Request $request, Form $form, DatabaseDriver $driver): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        $data = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'filter' => ['sometimes', 'array', 'max:20'],
            'filter.*' => ['nullable'],
            'trashed' => ['sometimes', 'boolean'],
        ]);
        $user = $this->user();
        $levels = $this->fieldAccess->resolve($user, $form->id, $form->uuid, $rt->definition, 'view');
        $q = DB::table($rt->table);
        if (($data['trashed'] ?? false) && $this->access->allows($user, "form.{$form->uuid}.restore")) {
            $q->whereNotNull('deleted_at');
        } else {
            $q->whereNull('deleted_at');
        }
        if (! empty($data['search'])) {
            $term = mb_strtolower(Unicode::normalizeArabic(trim($data['search'])));
            $driver->caseInsensitiveLike($q, 'search_text', $term);
        }
        foreach ($data['filter'] ?? [] as $key => $value) {
            $uuid = $rt->keys[$key] ?? null;
            if ($uuid === null || ($levels['fields'][$uuid] ?? 'hidden') === 'hidden' || $value === null || $value === '') {
                continue;
            }
            $f = $rt->fields[$uuid];
            $col = $rt->column($uuid);
            if ($col === null || ($col['encrypted'] ?? false) || ! ($f['table']['filterable'] ?? false)) {
                continue;
            }
            if ($col['type'] === 'bigint' && is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1 && ($table = $rt->targetTable($f)) !== null) {
                $q->where($col['name'], app(References::class)->ids($table, [$value])[strtolower($value)] ?? 0);
            } elseif (is_array($value) && (isset($value['from']) || isset($value['to']))) {
                if (($value['from'] ?? null) !== null) {
                    $q->where($col['name'], '>=', $value['from']);
                }
                if (($value['to'] ?? null) !== null) {
                    $q->where($col['name'], '<=', $value['to']);
                }
            } elseif (in_array($col['type'], ['string', 'code', 'text'], true)) {
                $driver->caseInsensitiveLike($q, $col['name'], (string) $value);
            } elseif (is_scalar($value)) {
                $q->where($col['name'], $col['type'] === 'bool' ? (int) filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value);
            }
        }
        $sort = $data['sort'] ?? null;
        $direction = $data['direction'] ?? 'desc';
        $sortColumn = match (true) {
            $sort === null, $sort === 'updated_at' => 'updated_at',
            $sort === 'created_at' => 'created_at',
            $sort === 'record_number' => 'record_number',
            isset($rt->keys[$sort]) && ($levels['fields'][$rt->keys[$sort]] ?? 'hidden') !== 'hidden' && ($rt->fields[$rt->keys[$sort]]['table']['sortable'] ?? false) => $rt->column($rt->keys[$sort])['name'] ?? 'updated_at',
            default => 'updated_at',
        };
        $total = (clone $q)->count();
        $perPage = $data['per_page'] ?? 25;
        $page = $data['page'] ?? 1;
        $rows = $q->orderBy($sortColumn, $direction)->orderBy('id', $direction)->forPage($page, $perPage)->get()->map(static fn ($r) => (array) $r)->all();
        $records = $this->store->hydrate($rt, $rows, false);

        return response()->json([
            'data' => $this->presenter->many($rt, $records, $levels['fields']),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage],
        ]);
    }

    public function show(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'view');
        $found = $this->store->find($rt, strtolower($record), $this->access->allows($this->user(), "form.{$form->uuid}.restore"));
        abort_if($found === null, 404, __('records.not_found'));
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'edit');
        $viewLevels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view');
        $merged = [];
        foreach ($viewLevels['fields'] as $uuid => $l) {
            $merged[$uuid] = $l === 'hidden' && ($levels['fields'][$uuid] ?? 'hidden') === 'hidden' ? 'hidden' : $l;
        }

        return response()->json(['data' => $this->presenter->present($rt, $found, $merged) + ['permissions' => [
            'edit' => $levels['modes']['edit'] && $found['system']['deleted_at'] === null,
            'delete' => $levels['modes']['delete'] && $found['system']['deleted_at'] === null,
            'restore' => $found['system']['deleted_at'] !== null && $this->access->allows($this->user(), "form.{$form->uuid}.restore"),
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
        $data = $request->validate(['values' => ['present', 'array'], 'row_version' => ['required_without:If-Match', 'nullable', 'integer', 'min:1']]);
        $expected = (int) ($data['row_version'] ?? $request->header('If-Match'));
        abort_if($expected < 1, 428, __('records.row_version_required'));

        return $this->run(function () use ($rt, $data, $request, $form, $record, $expected): JsonResponse {
            $result = $this->pipeline->update($rt, $this->user(), strtolower($record), $expected, $data['values'], $this->key($request));
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

        return $this->run(function () use ($rt, $request, $record, $expected): JsonResponse {
            $this->pipeline->delete($rt, $this->user(), strtolower($record), $expected, $this->key($request));

            return response()->json(null, 204);
        });
    }

    public function restore(Request $request, Form $form, string $record): JsonResponse
    {
        $rt = $this->runtime($form, 'restore');

        return $this->run(function () use ($rt, $request, $record): JsonResponse {
            $this->pipeline->restore($rt, $this->user(), strtolower($record), $this->key($request));

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
                    $q->where(static fn ($w) => $w->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%')->orWhere('email', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%'));
                }
            }
            if (! empty($data['uuids'])) {
                $q->whereIn('uuid', $data['uuids']);
            }
            $rows = $q->orderBy('id')->limit(500)->get();
            $titles = $refs->titles($rt, $f, $rows->pluck('uuid')->map(fn ($u) => strtolower((string) $u))->all());
            $items = $rows->map(static fn ($r) => ['value' => strtolower((string) $r->uuid), 'label' => $titles[strtolower((string) $r->uuid)] ?? (string) $r->uuid])->values()->all();
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

        return response()->json(['data' => array_map(static function ($r, $raw) use ($display, $previewKeys) {
            return [
                'value' => $r['uuid'],
                'label' => (string) ($display !== null ? ($raw->{$display} ?? $r['uuid']) : ($r['system']['record_number'] ?? $r['uuid'])),
                'preview' => array_intersect_key($r['values'], array_flip($previewKeys)),
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
        abort_if($found === null, 404);
        $levels = $this->fieldAccess->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view');
        $hiddenKeys = [];
        foreach ($levels['fields'] as $uuid => $l) {
            if ($l === 'hidden') {
                $hiddenKeys[$rt->fields[$uuid]['key']] = true;
            }
        }
        $entries = DB::table('audit_logs')->where('form_id', $form->id)->where('record_id', $found['id'])->orderByDesc('id')->limit(200)->get();
        $users = DB::table('users')->whereIn('id', $entries->pluck('actor_user_id')->filter())->pluck('name', 'id');

        return response()->json(['data' => $entries->map(static fn ($e) => [
            'event' => $e->event,
            'at' => Carbon::parse($e->occurred_at, 'UTC')->toIso8601ZuluString(),
            'by' => $users[$e->actor_user_id] ?? null,
            'changes' => array_values(array_filter(json_decode((string) $e->changes, true) ?: [], static fn ($c) => ! isset($hiddenKeys[$c['field_key']]))),
        ])->values()]);
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
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.view"), 404, __('records.form_unavailable'));
        if ($ability !== 'view') {
            abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.{$ability}"), 403, __('records.forbidden'));
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
