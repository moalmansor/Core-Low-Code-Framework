<?php

declare(strict_types=1);

namespace App\Modules\Actions\Http\Controllers;

use App\Modules\Actions\ActionDocument;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** A form's actions (specification §4.15), saved whole with a concurrency hash. Needs "Manage forms". */
final class ActionConfigController extends Controller
{
    public function __construct(private readonly ActionDocument $actions) {}

    public function show(Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => ['actions' => $this->actions->load($form), 'hash' => $this->actions->hash($form), 'published' => $runtimes->forForm($form) !== null]]);
    }

    public function update(Request $request, Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['actions' => ['present', 'array', 'max:100'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->actions->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('actions.changed_elsewhere'), 'code' => 'actions_changed', 'data' => ['actions' => $this->actions->load($form), 'hash' => $this->actions->hash($form)]], 409);
        }
        $this->actions->save($form, array_values((array) $request->input('actions')), (int) Auth::id());

        return $this->show($form, $runtimes);
    }
}
