<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\ApiTokens;
use App\Modules\Platform\OpenApiGenerator;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Runtime\RecordQuery;
use App\Modules\Workflow\Http\Controllers\RecordWorkflowController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The automatic REST API (specification §4.22): every published form and
 * collection by its key, for token holders. Each call is checked twice —
 * against the token's abilities here, then against its owner's permissions,
 * record scope, field access and status rules by the same record and
 * workflow endpoints the application uses, so the API can never do more than
 * its owner can in the browser. Writes are journalled with source `api`.
 */
final class DataApiController extends Controller
{
    public function index(Request $request, string $formKey): JsonResponse
    {
        return app(RecordController::class)->index($request, $this->form($formKey, false), app(RecordQuery::class));
    }

    public function show(string $formKey, string $record): JsonResponse
    {
        return app(RecordController::class)->show($this->form($formKey, false), $record);
    }

    public function store(Request $request, string $formKey): JsonResponse
    {
        return app(RecordController::class)->store($request, $this->form($formKey, true));
    }

    public function update(Request $request, string $formKey, string $record): JsonResponse
    {
        return app(RecordController::class)->update($request, $this->form($formKey, true), $record);
    }

    public function destroy(Request $request, string $formKey, string $record): JsonResponse
    {
        return app(RecordController::class)->destroy($request, $this->form($formKey, true), $record);
    }

    public function transition(Request $request, string $formKey, string $record, string $transition): JsonResponse
    {
        $form = $this->form($formKey, true);
        $uuid = DB::table('transitions')->where('form_id', $form->id)->where('key', strtolower($transition))->value('uuid');
        abort_if($uuid === null, 404, __('platform.unknown_transition'));

        return app(RecordWorkflowController::class)->perform($request, $form, $record, strtolower((string) $uuid));
    }

    public function openapi(Request $request, OpenApiGenerator $generator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($generator->document($user, ApiTokens::current($request)));
    }

    private function form(string $key, bool $write): Form
    {
        $form = Form::query()->where('key', strtolower($key))->whereIn('state', ['published', 'schema_inconsistent'])->first();
        $token = ApiTokens::current();
        // A form the token cannot reach looks like one that does not exist.
        abort_if($form === null || $token === null || ! $token->allowsForm((string) $form->uuid, false), 404, __('records.form_unavailable'));
        abort_if($write && ! $token->allowsForm((string) $form->uuid, true), 403, __('platform.token_read_only'));

        return $form;
    }
}
