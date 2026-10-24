<?php

declare(strict_types=1);

namespace App\Modules\External\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\RecordScope;
use App\Modules\External\AccessLinks;
use App\Modules\External\Models\AccessToken;
use App\Modules\External\Models\SignatureRequest;
use App\Modules\External\Signatures;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Links and signature requests sent from a record (specification §4.32,
 * ADR-0058). Anyone who can see the record sees what was sent from it;
 * sending needs the access the link or the document needs; revoking or
 * cancelling is for the sender or holders of "Manage external access".
 */
final class RecordSharingController extends Controller
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordPipeline $pipeline,
        private readonly RecordScope $scope,
        private readonly AccessLinks $links,
        private readonly Signatures $signatures,
    ) {}

    public function index(Form $form, string $record): JsonResponse
    {
        [$rt, $id] = $this->record($form, $record);
        $user = $this->user();
        $tokens = AccessToken::query()->where('form_id', $rt->form->id)->where('record_id', $id)->where('purpose', 'record_action')->orderByDesc('id')->limit(100)->get();
        $requests = SignatureRequest::query()->where('form_id', $rt->form->id)->where('record_id', $id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => [
            'links' => $tokens->map(fn (AccessToken $t) => $this->links->present($t) + ['can_revoke' => $this->mayManage($user, $t->created_by)])->values()->all(),
            'signatures' => $requests->map(fn (SignatureRequest $r) => $this->signatures->present($r) + ['can_cancel' => $this->mayManage($user, $r->created_by)])->values()->all(),
            'choices' => $this->links->choices($rt, $user, $id),
            'templates' => $this->signatures->templates($rt, $user, $id),
        ]]);
    }

    public function storeLink(Request $request, Form $form, string $record): JsonResponse
    {
        [$rt, $id] = $this->record($form, $record);
        try {
            $issued = $this->links->issue($rt, $this->user(), $id, (array) $request->input('link', []), app()->getLocale());
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        // The link is shown once; only its hash is kept.
        return response()->json(['data' => $this->links->present($issued['token']) + ['url' => $issued['url']]], 201);
    }

    public function revokeLink(string $link): JsonResponse
    {
        $t = Str::isUuid($link) ? AccessToken::query()->where('uuid', strtolower($link))->where('purpose', 'record_action')->first() : null;
        abort_if($t === null, 404);
        $this->visible($t->form_id, $t->record_id);
        abort_unless($this->mayManage($this->user(), $t->created_by), 403);
        $this->links->revoke($t, $this->user());

        return response()->json(null, 204);
    }

    public function storeSignature(Request $request, Form $form, string $record): JsonResponse
    {
        [$rt, $id] = $this->record($form, $record);
        try {
            $done = $this->signatures->request($rt, $this->user(), $id, (array) $request->input('signature', []), app()->getLocale());
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json(['data' => $this->signatures->present($done['request'])], 201);
    }

    public function cancelSignature(string $request): JsonResponse
    {
        $r = $this->signatureRequest($request);
        abort_unless($this->mayManage($this->user(), $r->created_by), 403);
        $this->signatures->cancel($r, $this->user());

        return response()->json(null, 204);
    }

    /** The signed PDF, for whoever can see the record. */
    public function signedFile(string $request): StreamedResponse
    {
        $file = $this->signatures->signed($this->signatureRequest($request));
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function signatureRequest(string $uuid): SignatureRequest
    {
        $r = Str::isUuid($uuid) ? SignatureRequest::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($r === null, 404);
        $this->visible($r->form_id, $r->record_id);

        return $r;
    }

    /** @return array{0: FormRuntime, 1: int} */
    private function record(Form $form, string $record): array
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null || $this->pipeline->permitted($rt, $this->user(), ["form.{$form->uuid}.view"]) === false, 404, __('records.form_unavailable'));
        $id = Str::isUuid($record) ? DB::table($rt->table)->where('uuid', strtolower($record))->whereNull('deleted_at')->value('id') : null;
        abort_if($id === null || ! $this->scope->allows($rt, $this->user(), 'view', (int) $id), 404, __('records.not_found'));

        return [$rt, (int) $id];
    }

    private function visible(int $formId, int $recordId): void
    {
        $form = Form::query()->find($formId);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        abort_if($rt === null || ! $this->scope->allows($rt, $this->user(), 'view', $recordId), 404);
    }

    private function mayManage(User $user, ?int $creator): bool
    {
        return $creator === $user->id || Gate::allows('system.manage_external_access');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
