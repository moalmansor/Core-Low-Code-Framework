<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inline sub-forms of a linked form (specification §4.5, architecture §11.1):
 * the linked form's records live in its own table with a foreign key to the
 * parent record. Listing needs view on both forms; adding needs edit on the
 * parent and create on the linked form, and goes through the linked form's
 * record pipeline with the parent key set at insert.
 */
final class SubformController extends Controller
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly RecordPipeline $pipeline,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly AccessResolver $access,
    ) {}

    public function index(Request $request, Form $form, string $record, string $group): JsonResponse
    {
        [$rt, $parent, $target, $column] = $this->resolve($form, $record, $group, 'view');
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']]);
        $q = DB::table($target->table)->whereNull('deleted_at')->where($column, $parent['id']);
        $total = (clone $q)->count();
        $page = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 25;
        $rows = $q->orderBy('id')->forPage($page, $perPage)->get()->map(static fn ($r) => (array) $r)->all();
        $levels = $this->fieldAccess->resolve($this->user(), $target->form->id, $target->form->uuid, $target->definition, 'view');

        return response()->json([
            'data' => $this->presenter->many($target, $this->store->hydrate($target, $rows, false), $levels['fields']),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'form' => $target->form->uuid,
                'can_add' => $this->allows($rt->form, 'edit') && $this->allows($target->form, 'create')],
        ]);
    }

    public function store(Request $request, Form $form, string $record, string $group): JsonResponse
    {
        [, $parent, $target, $column] = $this->resolve($form, $record, $group, 'edit');
        abort_unless($this->allows($target->form, 'create'), 403, __('records.forbidden'));
        $data = $request->validate(['values' => ['present', 'array']]);
        $header = (string) $request->header('Idempotency-Key', '');
        $key = preg_match('/^[A-Za-z0-9-]{16,64}$/', $header) === 1 ? $header : (string) Str::uuid7();
        try {
            $result = $this->pipeline->create($target, $this->user(), $data['values'], [], $key, 'ui', [$column => $parent['id']]);
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }
        $levels = $this->fieldAccess->resolve($this->user(), $target->form->id, $target->form->uuid, $target->definition, 'view');

        return response()->json(['data' => $this->presenter->present($target, $this->store->findById($target, $result['id']), $levels['fields'])], 201);
    }

    /** @return array{0: FormRuntime, 1: array<string, mixed>, 2: FormRuntime, 3: string} */
    private function resolve(Form $form, string $record, string $groupKey, string $parentAbility): array
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null || $form->state !== 'published' || ! $this->allows($form, 'view') || ! $this->allows($form, $parentAbility), 404, __('records.form_unavailable'));
        $parent = $this->store->find($rt, strtolower($record));
        abort_if($parent === null, 404, __('records.not_found'));
        $group = collect($rt->groups)->first(static fn (array $g) => $g['key'] === $groupKey && $g['type'] === 'subform');
        $relationUuid = $group['subform']['relation'] ?? null;
        abort_if($relationUuid === null || ! isset($rt->relations[$relationUuid]), 404);
        $targetForm = Form::query()->where('uuid', $rt->relations[$relationUuid]['target'])->first();
        $target = $targetForm === null ? null : $this->runtimes->forForm($targetForm);
        abort_if($target === null || $targetForm->state !== 'published' || ! $this->allows($targetForm, 'view'), 404, __('records.form_unavailable'));
        $column = collect($rt->definition['schema']['external'] ?? [])->first(static fn (array $e) => $e['relation'] === $relationUuid)['column']['name'] ?? null;
        abort_if($column === null, 404);

        return [$rt, $parent, $target, (string) $column];
    }

    private function allows(Form $form, string $ability): bool
    {
        return $this->access->allows($this->user(), "form.{$form->uuid}.{$ability}");
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
