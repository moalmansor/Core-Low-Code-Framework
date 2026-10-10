<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Http\Concerns\ResolvesRecords;
use App\Modules\Views\PrintLayouts;
use App\Modules\Views\PrintRenderer;
use App\Modules\Views\ReferencePreviews;
use App\Modules\Views\ViewPanels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * View Mode, Edit Mode and print (architecture §21 Views): admin documents
 * for panels, reference previews and print layouts (Manage Forms, with a
 * concurrency hash), and the runtime endpoints: a record's panels with their
 * data, its attachments, a lookup's preview card, and the print view or PDF.
 */
final class RecordPagesController extends Controller
{
    use ResolvesRecords;

    public function __construct(
        private readonly ViewPanels $panels,
        private readonly ReferencePreviews $previews,
        private readonly PrintLayouts $layouts,
    ) {}

    public function panelsDocument(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => ['panels' => $this->panels->load($form), 'hash' => $this->panels->hash($form)]]);
    }

    public function savePanels(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['panels' => ['present', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->panels->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('views.changed_elsewhere'), 'code' => 'panels_changed', 'data' => ['panels' => $this->panels->load($form), 'hash' => $this->panels->hash($form)]], 409);
        }
        $this->panels->save($form, array_values(json_decode((string) json_encode($request->input('panels')), true)), (int) Auth::id());

        return $this->panelsDocument($form);
    }

    public function previewsDocument(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => $this->previews->load($form) + ['hash' => $this->previews->hash($form)]]);
    }

    public function savePreviews(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['default' => ['present', 'nullable', 'array'], 'fields' => ['present', 'array', 'max:100'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->previews->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('views.changed_elsewhere'), 'code' => 'previews_changed', 'data' => $this->previews->load($form) + ['hash' => $this->previews->hash($form)]], 409);
        }
        $this->previews->save($form, json_decode((string) json_encode(['default' => $request->input('default'), 'fields' => $request->input('fields')]), true), (int) Auth::id());

        return $this->previewsDocument($form);
    }

    public function layoutsDocument(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => ['layouts' => $this->layouts->load($form), 'hash' => $this->layouts->hash($form)]]);
    }

    public function saveLayouts(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['layouts' => ['present', 'array', 'max:20'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->layouts->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('views.changed_elsewhere'), 'code' => 'layouts_changed', 'data' => ['layouts' => $this->layouts->load($form), 'hash' => $this->layouts->hash($form)]], 409);
        }
        $this->layouts->save($form, array_values(json_decode((string) json_encode($request->input('layouts')), true)), (int) Auth::id());

        return $this->layoutsDocument($form);
    }

    /** The record's View Mode panels with their data. */
    public function recordPanels(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record, 'view', true);

        return response()->json(['data' => $this->panels->render($rt, $found, $this->currentUser())]);
    }

    /** Record attachments: files of the record outside file fields (transition and approval evidence). */
    public function attachments(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record, 'view', true);
        $files = DB::table('files')->where('form_id', $form->id)->where('record_id', $found['id'])->whereNull('field_id')->whereNull('deleted_at')
            ->whereIn('owner_type', ['record', 'status_history', 'approval_request'])->orderByDesc('id')->limit(500)->get();
        $users = DB::table('users')->whereIn('id', $files->pluck('uploaded_by')->filter())->pluck('name', 'id');

        return response()->json(['data' => $files->map(static fn ($f) => [
            'uuid' => strtolower((string) $f->uuid), 'name' => $f->original_name, 'size' => (int) $f->size_bytes, 'mime' => $f->mime_type,
            'source' => $f->owner_type, 'by' => $users[$f->uploaded_by] ?? null, 'at' => $f->created_at,
        ])->values()]);
    }

    /** The preview card of the record picked in a lookup field. */
    public function preview(Form $form, string $field, string $value): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $uuid = $rt->keys[$field] ?? null;
        if ($uuid === null) {
            foreach ($rt->repeaters as $rep) {
                $uuid ??= $rep['fields'][$field] ?? null;
            }
        }
        abort_if($uuid === null || $rt->targetFormUuid($rt->fields[$uuid]) === null, 404);
        $user = $this->currentUser();
        $create = app(FieldAccessResolver::class)->resolve($user, $form->id, $form->uuid, $rt->definition, 'create');
        $edit = app(FieldAccessResolver::class)->resolve($user, $form->id, $form->uuid, $rt->definition, 'edit');
        abort_if(($create['fields'][$uuid] ?? 'hidden') === 'hidden' && ($edit['fields'][$uuid] ?? 'hidden') === 'hidden', 404);
        $card = $this->previews->card($rt, $rt->fields[$uuid], $value, $user);
        abort_if($card === null, 404, __('records.not_found'));

        return response()->json(['data' => $card]);
    }

    /** The print view (HTML) or PDF of a record with a print layout. */
    public function print(Request $request, Form $form, string $record, PrintRenderer $renderer): Response
    {
        $rt = $this->runtimeFor($form, 'print');
        $data = $request->validate(['layout' => ['sometimes', 'nullable', 'string', 'max:48'], 'format' => ['sometimes', Rule::in(['html', 'pdf'])]]);
        $found = $this->recordFor($rt, $record, 'view');
        $layouts = $this->layouts->load($form);
        $layout = null;
        foreach ($layouts as $l) {
            if (($data['layout'] ?? null) === $l['key'] || (($data['layout'] ?? null) === null && $l['default'])) {
                $layout = $l;
            }
        }
        abort_if(($data['layout'] ?? null) !== null && $layout === null, 404);
        $html = $renderer->html($rt, $found, $this->currentUser(), $layout);
        app(AuditWriter::class)->record('record.printed', 'export', null, 'record', $found['id'], ['form' => $form->key, 'layout' => $layout['key'] ?? null, 'format' => $data['format'] ?? 'html'], $this->currentUser()->id, null, $form->id, $found['id']);
        if (($data['format'] ?? 'html') === 'pdf') {
            $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $form->key.'-'.($found['system']['record_number'] ?? substr($found['uuid'], 0, 8))).'.pdf';

            return response($renderer->pdf($html, $layout), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }
}
