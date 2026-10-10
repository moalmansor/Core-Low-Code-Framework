<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Assignment\Membership;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Http\Concerns\ResolvesRecords;
use App\Modules\Views\ViewRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Saved personal and shared views (specification §4.14): a user saves the
 * table state (columns, order, widths, filters, sort, page size, search) on
 * top of a view they may use, and may share it with roles, departments,
 * users or everyone. Only the owner changes or deletes it; a shared view
 * never widens access, because every list still goes through the view's
 * columns the reader can see and their record scope.
 */
final class SavedViewController extends Controller
{
    use ResolvesRecords;

    public function __construct(private readonly ViewRuntime $views, private readonly Membership $members, private readonly AuditWriter $audit) {}

    public function index(Form $form): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $user = $this->currentUser();
        $allowed = array_column($this->views->available($rt, $user), 'uuid');
        $subjects = $this->members->subjectsOf($user);
        $q = DB::table('saved_views')->join('views', 'views.id', '=', 'saved_views.view_id')->where('saved_views.form_id', $form->id)
            ->where(static function ($w) use ($user, $subjects): void {
                $w->where('saved_views.owner_user_id', $user->id)->orWhere(static function ($s) use ($subjects): void {
                    $s->where('saved_views.is_shared', true)->whereExists(static function ($e) use ($subjects): void {
                        $e->selectRaw('1')->from('saved_view_shares')->whereColumn('saved_view_shares.saved_view_id', 'saved_views.id')
                            ->where(static function ($x) use ($subjects): void {
                                $x->where('subject_type', 'everyone');
                                foreach ($subjects as $type => $ids) {
                                    if ($ids !== []) {
                                        $x->orWhere(static fn ($y) => $y->where('subject_type', $type)->whereIn('subject_id', $ids));
                                    }
                                }
                            });
                    });
                });
            })->orderBy('saved_views.name')->get(['saved_views.*', 'views.uuid as view_uuid']);
        $owners = DB::table('users')->whereIn('id', $q->pluck('owner_user_id'))->pluck('name', 'id');

        return response()->json(['data' => $q->filter(static fn ($s) => in_array(strtolower((string) $s->view_uuid), $allowed, true))->map(fn ($s) => $this->present($s, $owners[$s->owner_user_id] ?? null, (int) $s->owner_user_id === $user->id))->values()]);
    }

    public function store(Request $request, Form $form): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $data = $request->validate($this->rules(true));
        $user = $this->currentUser();
        $view = $this->views->pick($rt, $user, strtolower($data['view']));
        abort_if($view === null, 422, __('views.not_allowed'));
        $viewId = (int) DB::table('views')->where('uuid', $view['uuid'])->value('id');
        $uuid = (string) Str::uuid7();
        DB::transaction(function () use ($form, $data, $user, $viewId, $uuid): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            if ($data['is_default'] ?? false) {
                DB::table('saved_views')->where('form_id', $form->id)->where('owner_user_id', $user->id)->update(['is_default' => false]);
            }
            $id = (int) DB::table('saved_views')->insertGetId([
                'uuid' => $uuid, 'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now, 'form_id' => $form->id,
                'view_id' => $viewId, 'owner_user_id' => $user->id, 'name' => $data['name'], 'state' => json_encode($data['state']),
                'is_shared' => ($data['shares'] ?? []) !== [], 'is_default' => (bool) ($data['is_default'] ?? false),
            ]);
            $this->shares($id, $data['shares'] ?? [], $now);
            $this->audit->record('saved_view.created', 'config', null, 'saved_view', $id, ['form' => $form->key, 'shared' => ($data['shares'] ?? []) !== []], $user->id);
        });

        return response()->json(['data' => ['uuid' => $uuid]], 201);
    }

    public function update(Request $request, Form $form, string $saved): JsonResponse
    {
        $this->runtimeFor($form);
        $row = $this->owned($form, $saved);
        $data = $request->validate($this->rules(false));
        DB::transaction(function () use ($form, $row, $data): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            if ($data['is_default'] ?? false) {
                DB::table('saved_views')->where('form_id', $form->id)->where('owner_user_id', $row->owner_user_id)->update(['is_default' => false]);
            }
            $update = ['updated_at' => $now];
            foreach (['name' => 'name', 'is_default' => 'is_default'] as $in => $col) {
                if (array_key_exists($in, $data)) {
                    $update[$col] = $data[$in];
                }
            }
            if (array_key_exists('state', $data)) {
                $update['state'] = json_encode($data['state']);
            }
            if (array_key_exists('shares', $data)) {
                $update['is_shared'] = $data['shares'] !== [];
                DB::table('saved_view_shares')->where('saved_view_id', $row->id)->delete();
                $this->shares((int) $row->id, $data['shares'], $now);
            }
            DB::table('saved_views')->where('id', $row->id)->update($update);
        });

        return response()->json(null, 204);
    }

    public function destroy(Form $form, string $saved): JsonResponse
    {
        $this->runtimeFor($form);
        $row = $this->owned($form, $saved);
        DB::table('saved_view_shares')->where('saved_view_id', $row->id)->delete();
        DB::table('saved_views')->where('id', $row->id)->delete();
        $this->audit->record('saved_view.deleted', 'config', null, 'saved_view', (int) $row->id, ['form' => $form->key], $this->currentUser()->id);

        return response()->json(null, 204);
    }

    private function owned(Form $form, string $uuid): object
    {
        $row = DB::table('saved_views')->where('form_id', $form->id)->where('uuid', strtolower($uuid))->first();
        abort_if($row === null, 404);
        abort_unless((int) $row->owner_user_id === $this->currentUser()->id, 403, __('views.not_owner'));

        return $row;
    }

    /** @param  list<array{type: string, uuid?: string|null}>  $shares */
    private function shares(int $savedId, array $shares, string $now): void
    {
        foreach ($shares as $s) {
            $id = $s['type'] === 'everyone' ? null : $this->members->idOf($s['type'], isset($s['uuid']) ? strtolower($s['uuid']) : null);
            abort_if($s['type'] !== 'everyone' && $id === null, 422, __('validation.exists', ['attribute' => 'shares']));
            DB::table('saved_view_shares')->insert(['saved_view_id' => $savedId, 'subject_type' => $s['type'], 'subject_id' => $id, 'created_at' => $now]);
        }
    }

    /** @return array<string, list<mixed>> */
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'view' => [$create ? 'required' : 'prohibited', 'uuid'],
            'name' => [$req, 'string', 'max:120'],
            'state' => [$req, 'array'],
            'state.columns' => ['sometimes', 'array', 'max:60'],
            'state.columns.*' => ['string', 'max:200'],
            'state.widths' => ['sometimes', 'array', 'max:60'],
            'state.widths.*' => ['integer', 'between:40,1200'],
            'state.filters' => ['sometimes', 'array', 'max:30'],
            'state.sort' => ['sometimes', 'nullable', 'array'],
            'state.sort.key' => ['sometimes', 'string', 'max:200'],
            'state.sort.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'state.page_size' => ['sometimes', 'integer', 'between:5,100'],
            'state.search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'is_default' => ['sometimes', 'boolean'],
            'shares' => ['sometimes', 'array', 'max:50'],
            'shares.*.type' => ['required', Rule::in(['role', 'department', 'user', 'everyone'])],
            'shares.*.uuid' => ['required_unless:shares.*.type,everyone', 'nullable', 'uuid'],
        ];
    }

    /** @return array<string, mixed> */
    private function present(object $s, ?string $owner, bool $mine): array
    {
        $shares = DB::table('saved_view_shares')->where('saved_view_id', $s->id)->get();

        return [
            'uuid' => strtolower((string) $s->uuid), 'name' => $s->name, 'view' => strtolower((string) $s->view_uuid),
            'state' => json_decode((string) $s->state, true) ?: [], 'is_default' => (bool) $s->is_default, 'is_shared' => (bool) $s->is_shared,
            'owner' => $owner, 'mine' => $mine,
            'shares' => $mine ? $shares->map(fn ($x) => ['type' => $x->subject_type, 'uuid' => $x->subject_id === null ? null : $this->members->uuidOf($x->subject_type, (int) $x->subject_id)])->values() : [],
        ];
    }
}
