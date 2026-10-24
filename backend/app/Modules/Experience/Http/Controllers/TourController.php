<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Experience\Models\Tour;
use App\Modules\Experience\Tours;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Guided tours (specification §4.37, ADR-0065). Building them needs "Manage
 * help content"; taking one needs only access to where it runs.
 */
final class TourController extends Controller
{
    public function __construct(private readonly Tours $tours) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_help_content');

        return response()->json(['data' => Tour::query()->orderBy('id')->limit(1000)->get()->map(fn (Tour $t) => $this->tours->present($t))->values()->all()]);
    }

    public function show(string $tour): JsonResponse
    {
        Gate::authorize('system.manage_help_content');

        return response()->json(['data' => $this->tours->present($this->find($tour))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $t = $this->tours->save(null, $this->tours->check((array) $request->input('tour', [])), $this->user());

        return response()->json(['data' => $this->tours->present($t)], 201);
    }

    public function update(Request $request, string $tour): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $t = $this->find($tour);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->tours->present($t)['hash'], (string) $request->input('base_hash')), 409, __('experience.changed_meanwhile'));
        $t = $this->tours->save($t, $this->tours->check((array) $request->input('tour', [])), $this->user());

        return response()->json(['data' => $this->tours->present($t)]);
    }

    public function destroy(string $tour): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $this->tours->delete($this->find($tour), $this->user());

        return response()->json(null, 204);
    }

    /** Shows the tour again, to everyone or to the holders of some roles. */
    public function reset(Request $request, string $tour): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $data = $request->validate(['roles' => ['sometimes', 'array', 'max:100'], 'roles.*' => ['uuid']]);
        $ids = DB::table('roles')->whereIn('uuid', array_map('strtolower', $data['roles'] ?? []))->pluck('id')->map(static fn ($v) => (int) $v)->all();
        abort_if(count($ids) !== count($data['roles'] ?? []), 422, __('reports.unknown_role'));

        return response()->json(['data' => ['people' => $this->tours->reset($this->find($tour), $ids, $this->user())]]);
    }

    /** The tours where the person is: on a form's screens, or a place. */
    public function mine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form' => ['required_without:place', 'nullable', 'uuid'],
            'place' => ['required_without:form', 'nullable', 'string', 'max:128'],
        ]);

        return response()->json(['data' => $this->tours->forPlace($this->user(), $data['form'] ?? null, $data['place'] ?? null)]);
    }

    public function progress(Request $request, string $tour): JsonResponse
    {
        $data = $request->validate(['outcome' => ['required', Rule::in(['completed', 'dismissed'])]]);
        $t = $this->find($tour);
        // Only a tour the person may take, where it runs.
        $form = $t->form_id === null ? null : (string) DB::table('forms')->where('id', $t->form_id)->value('uuid');
        abort_unless(collect($this->tours->forPlace($this->user(), $form, $t->route_key))->contains('uuid', strtolower($t->uuid)), 404);
        $this->tours->record($t, $this->user(), $data['outcome']);

        return response()->json(null, 204);
    }

    public function resetMine(): JsonResponse
    {
        $this->tours->resetMine($this->user());

        return response()->json(null, 204);
    }

    private function find(string $uuid): Tour
    {
        $t = Str::isUuid($uuid) ? Tour::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($t === null, 404);

        return $t;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
