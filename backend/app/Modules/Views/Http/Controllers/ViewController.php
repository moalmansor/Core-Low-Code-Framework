<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Views\FieldTree;
use App\Modules\Views\ViewDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table views of a form (architecture §21 Views): the views document with
 * its concurrency hash, and the relation tree an admin picks column and
 * filter paths from (this form's fields and those of the forms it links to).
 */
final class ViewController extends Controller
{
    public function __construct(private readonly ViewDocument $views) {}

    public function show(Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $rt = $runtimes->forForm($form);

        return response()->json(['data' => ['views' => $this->views->load($form), 'hash' => $this->views->hash($form), 'published' => $rt !== null, 'fields' => $rt === null ? [] : app(FieldTree::class)->build($rt)]]);
    }

    public function update(Request $request, Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['views' => ['present', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->views->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('views.changed_elsewhere'), 'code' => 'views_changed', 'data' => ['views' => $this->views->load($form), 'hash' => $this->views->hash($form)]], 409);
        }
        $this->views->save($form, array_values(json_decode((string) json_encode($request->input('views')), true)), (int) Auth::id());

        return $this->show($form, $runtimes);
    }

    /** The fields reachable from a form, for the relation-path pickers of every configuration screen. */
    public function fieldTree(Form $form, FormRuntimes $runtimes, FieldTree $tree): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_forms', 'system.manage_notifications', 'system.manage_document_templates', 'system.manage_automations']), 403);
        $rt = $runtimes->forForm($form);

        return response()->json(['data' => ['published' => $rt !== null, 'fields' => $rt === null ? [] : $tree->build($rt)]]);
    }
}
