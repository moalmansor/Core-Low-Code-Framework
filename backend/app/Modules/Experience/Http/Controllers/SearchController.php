<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Experience\GlobalSearch;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Global search and its configuration (specification §4.29, ADR-0054). */
final class SearchController extends Controller
{
    public function __construct(private readonly GlobalSearch $search) {}

    /** Every form and collection with its search configuration. */
    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');

        return response()->json(['data' => Form::query()->whereIn('kind', ['form', 'collection'])->orderBy('key')->get()
            ->map(fn (Form $f) => $this->search->present($f))->values()->all()]);
    }

    public function update(Request $request, string $form): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $f = $this->form($form);
        /** @var User $user */
        $user = Auth::user();
        $this->search->save($f, (array) $request->input('config', []), $user);

        return response()->json(['data' => $this->search->present($f)]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        /** @var User $user */
        $user = Auth::user();

        return response()->json(['data' => $this->search->search($user, $data['q'])]);
    }

    private function form(string $uuid): Form
    {
        $f = Str::isUuid($uuid) ? Form::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($f === null, 404);

        return $f;
    }
}
