<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Record comments (architecture §21.2 "Comments & attachments"), when the form allows comments. */
final class CommentController extends Controller
{
    public function __construct(private readonly FormRuntimes $runtimes, private readonly AccessResolver $access) {}

    public function index(Form $form, string $record): JsonResponse
    {
        [$recordId] = $this->guard($form, $record, 'view');
        $rows = DB::table('record_comments')->where('form_id', $form->id)->where('record_id', $recordId)->whereNull('deleted_at')->orderBy('created_at')->get();
        $users = DB::table('users')->whereIn('id', $rows->pluck('author_user_id'))->get(['id', 'uuid', 'name'])->keyBy('id');
        $ids = $rows->pluck('uuid', 'id');

        return response()->json(['data' => $rows->map(static fn ($c) => [
            'uuid' => strtolower((string) $c->uuid),
            'parent' => $c->parent_id === null ? null : strtolower((string) ($ids[$c->parent_id] ?? '')),
            'body' => $c->body,
            'author' => ['uuid' => strtolower((string) ($users[$c->author_user_id]->uuid ?? '')), 'name' => $users[$c->author_user_id]->name ?? null],
            'created_at' => Carbon::parse($c->created_at, 'UTC')->toIso8601ZuluString(),
            'mine' => (int) $c->author_user_id === (int) Auth::id(),
        ])->values()]);
    }

    public function store(Request $request, Form $form, string $record, HtmlSanitizer $sanitizer, AuditWriter $audit): JsonResponse
    {
        [$recordId] = $this->guard($form, $record, 'view');
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'parent' => ['sometimes', 'nullable', 'uuid']]);
        $parentId = isset($data['parent']) ? DB::table('record_comments')->where('uuid', $data['parent'])->where('record_id', $recordId)->value('id') : null;
        $uuid = (string) Str::uuid7();
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = DB::table('record_comments')->insertGetId([
            'uuid' => $uuid, 'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'form_id' => $form->id, 'record_id' => $recordId, 'parent_id' => $parentId,
            'body' => $sanitizer->clean($data['body']), 'author_user_id' => Auth::id(),
        ]);
        $audit->record('record.comment_added', 'data', null, 'record', $recordId, ['comment' => $uuid], null, null, $form->id, $recordId);

        return response()->json(['data' => ['uuid' => $uuid, 'id' => $id]], 201);
    }

    public function destroy(Form $form, string $record, string $comment, AuditWriter $audit): JsonResponse
    {
        [$recordId] = $this->guard($form, $record, 'view');
        $row = DB::table('record_comments')->where('uuid', $comment)->where('record_id', $recordId)->whereNull('deleted_at')->first();
        abort_if($row === null, 404);
        abort_unless((int) $row->author_user_id === (int) Auth::id() || $this->access->allows($this->user(), "form.{$form->uuid}.edit"), 403);
        DB::table('record_comments')->where('id', $row->id)->update(['deleted_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'deleted_by' => Auth::id()]);
        $audit->record('record.comment_deleted', 'data', null, 'record', $recordId, ['comment' => $comment], null, null, $form->id, $recordId);

        return response()->json(null, 204);
    }

    /** @return array{0: int} */
    private function guard(Form $form, string $record, string $ability): array
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null, 404);
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.{$ability}"), 404);
        abort_if(($rt->definition['form']['settings']['allowComments'] ?? true) === false, 404);
        $id = DB::table($rt->table)->where('uuid', strtolower($record))->whereNull('deleted_at')->value('id');
        abort_if($id === null, 404);

        return [(int) $id];
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
