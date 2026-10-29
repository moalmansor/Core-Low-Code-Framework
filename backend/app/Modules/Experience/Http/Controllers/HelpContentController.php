<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Experience\HelpContents;
use App\Modules\Experience\Models\HelpContent;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Help content (specification §4.37, ADR-0064). Writing it needs "Manage help
 * content"; reading it needs only access to what it is about.
 */
final class HelpContentController extends Controller
{
    public function __construct(private readonly HelpContents $help) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $data = $request->validate(['target_type' => ['sometimes', 'nullable', Rule::in(HelpContents::TARGETS)]]);

        return response()->json(['data' => HelpContent::query()
            ->when(isset($data['target_type']), static fn ($q) => $q->where('target_type', $data['target_type']))
            ->orderBy('target_type')->orderBy('sort_order')->orderBy('id')->limit(1000)->get()
            ->map(fn (HelpContent $h) => $this->help->present($h))->values()->all()]);
    }

    /** What help can be written for (the editor's pickers). */
    public function targets(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $data = $request->validate(['form' => ['sometimes', 'nullable', 'uuid']]);

        return response()->json(['data' => $this->help->targets($data['form'] ?? null)]);
    }

    public function show(string $help): JsonResponse
    {
        Gate::authorize('system.manage_help_content');

        return response()->json(['data' => $this->help->present($this->find($help))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $h = $this->help->save(null, $this->help->check((array) $request->input('help', [])), $this->user());

        return response()->json(['data' => $this->help->present($h)], 201);
    }

    public function update(Request $request, string $help): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $h = $this->find($help);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->help->present($h)['hash'], (string) $request->input('base_hash')), 409, __('experience.changed_meanwhile'));
        $h = $this->help->save($h, $this->help->check((array) $request->input('help', [])), $this->user());

        return response()->json(['data' => $this->help->present($h)]);
    }

    public function destroy(string $help): JsonResponse
    {
        Gate::authorize('system.manage_help_content');
        $this->help->delete($this->find($help), $this->user());

        return response()->json(null, 204);
    }

    /** Published help for what the person is looking at. */
    public function context(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['form', 'page', 'application', 'admin_area'])],
            'target' => ['required', 'string', 'max:64'],
        ]);

        return response()->json(['data' => $this->help->forContext($this->user(), $data['type'], $data['target'])]);
    }

    /** A help page of its own. */
    public function page(string $help): JsonResponse
    {
        $page = $this->help->page($this->user(), $this->find($help));
        abort_if($page === null, 404);

        return response()->json(['data' => $page]);
    }

    private function find(string $uuid): HelpContent
    {
        $h = Str::isUuid($uuid) ? HelpContent::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($h === null, 404);

        return $h;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
