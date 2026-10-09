<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Views\RelationPaths;
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

        return response()->json(['data' => ['views' => $this->views->load($form), 'hash' => $this->views->hash($form), 'published' => $rt !== null, 'fields' => $rt === null ? [] : $this->tree($rt, [], 0)]]);
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

    /**
     * Fields reachable from a form, following to-one and to-many references
     * up to the path depth limit.
     *
     * @param  list<string>  $prefix
     * @return list<array<string, mixed>>
     */
    private function tree(FormRuntime $rt, array $prefix, int $depth): array
    {
        $out = [];
        foreach ($rt->mainFields() as $f) {
            if (! $rt->isStored($f) || ($f['flags']['encrypted'] ?? false)) {
                continue;
            }
            $node = ['key' => $f['key'], 'path' => [...$prefix, $f['key']], 'type' => $f['type'], 'label' => $f['i18n']['label'] ?? [], 'children' => []];
            $target = $rt->targetFormUuid($f);
            if ($target !== null && $depth < RelationPaths::MAX_DEPTH - 2) {
                $trt = app(FormRuntimes::class)->forUuid($target);
                if ($trt !== null && $trt->form->id !== $rt->form->id) {
                    $node['children'] = $this->tree($trt, [...$prefix, $f['key']], $depth + 1);
                    $node['form'] = ['uuid' => $trt->form->uuid, 'key' => $trt->form->key];
                }
            }
            $out[] = $node;
        }
        foreach (['@status', '@record_number', '@created_at', '@updated_at'] as $system) {
            $out[] = ['key' => $system, 'path' => [...$prefix, $system], 'type' => 'system', 'label' => ['en' => __('views.system_'.substr($system, 1), [], 'en'), 'ar' => __('views.system_'.substr($system, 1), [], 'ar')], 'children' => []];
        }

        return $out;
    }
}
