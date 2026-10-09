<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Assignment\ApprovalService;
use App\Modules\Assignment\Claims;
use App\Modules\Assignment\DelegationResolver;
use App\Modules\Assignment\Membership;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Justification\JustificationPresenter;
use App\Modules\Records\Http\Concerns\ResolvesRecords;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Workflow\Runtime\WorkflowEngine;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The workflow of one record (architecture §21 Workflow): its status, the
 * transitions the user may take, the pending approval, the claim and the
 * assignments; performing a transition; and the status history timeline.
 */
final class RecordWorkflowController extends Controller
{
    use ResolvesRecords;

    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly RecordPipeline $pipeline,
        private readonly ApprovalService $approvals,
        private readonly Claims $claims,
        private readonly Membership $members,
    ) {}

    public function show(Form $form, string $record, RecordScope $scope): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record, 'view', true);
        $wf = WorkflowRuntime::for($rt);
        $user = $this->currentUser();
        $editable = $found['system']['deleted_at'] === null && $scope->allows($rt, $user, 'edit', $found['id']);
        $assignments = DB::table('assignments')->where('form_id', $form->id)->where('record_id', $found['id'])->where('status', 'active')->orderBy('id')->get();
        $subjects = $this->members->subjectsOf($user);
        $timers = DB::table('sla_timers')->where('form_id', $form->id)->where('record_id', $found['id'])->whereIn('state', ['running', 'warned', 'breached'])->get();

        return response()->json(['data' => [
            'enabled' => $wf->enabled(),
            'status' => $this->engine->statusPayload($wf, $found['system']['status_id'] ?? null),
            'transitions' => $editable ? [...$this->engine->available($rt, $found, $user), ...$this->delegatedTransitions($rt, $found)] : [],
            'approval' => $this->approvals->summary($rt, $found['id'], $user),
            'claim' => $this->claims->present($this->claims->holder($form->id, $found['id'])),
            'assignments' => $assignments->map(fn ($a) => [
                'uuid' => strtolower((string) $a->uuid),
                'assignee' => ['type' => $a->assignee_type, 'uuid' => $this->members->uuidOf($a->assignee_type, (int) $a->assignee_id), 'name' => $this->subjectName($a->assignee_type, (int) $a->assignee_id)],
                'approval' => $a->approval_request_id !== null,
                'due_at' => $a->due_at === null ? null : Carbon::parse($a->due_at, 'UTC')->toIso8601ZuluString(),
                'priority' => (int) $a->priority,
                'mine' => in_array((int) $a->assignee_id, $subjects[$a->assignee_type] ?? [], true),
            ])->values(),
            'sla' => $timers->map(static fn ($t) => [
                'state' => $t->state,
                'due_at' => Carbon::parse($t->due_at, 'UTC')->toIso8601ZuluString(),
                'started_at' => Carbon::parse($t->started_at, 'UTC')->toIso8601ZuluString(),
            ])->values(),
            'can' => [
                'assign' => $editable && app(AccessResolver::class)->allows($user, 'system.assign_records'),
                'reassign' => $editable && app(AccessResolver::class)->allows($user, 'system.reassign_records'),
            ],
        ]]);
    }

    public function transitions(Form $form, string $record, RecordScope $scope): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record);
        $editable = $scope->allows($rt, $this->currentUser(), 'edit', $found['id']);

        return response()->json(['data' => $editable ? [...$this->engine->available($rt, $found, $this->currentUser()), ...$this->delegatedTransitions($rt, $found)] : []]);
    }

    public function perform(Request $request, Form $form, string $record, string $transition): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $data = $request->validate([
            'row_version' => ['required', 'integer', 'min:1'],
            'values' => ['sometimes', 'array'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => ['uuid'],
        ] + RecordController::justificationRules());

        return $this->runRecord(function () use ($rt, $data, $record, $transition): JsonResponse {
            $result = $this->pipeline->transition($rt, $this->currentUser(), strtolower($record), strtolower($transition), (int) $data['row_version'], [
                'values' => $data['values'] ?? [], 'comment' => $data['comment'] ?? null, 'attachments' => $data['attachments'] ?? [], 'justification' => $data['justification'] ?? null,
            ], $this->idempotencyKey(), request()->is('api/v1/data/*') ? 'api' : 'ui');

            return response()->json(['data' => $result]);
        });
    }

    /** The status history timeline (View Mode), with comments, attachments and justifications for their viewers. */
    public function history(Form $form, string $record, JustificationPresenter $justifications, AccessResolver $access): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record, 'view', true);
        $wf = WorkflowRuntime::for($rt);
        $rows = DB::table('status_history')->where('form_id', $form->id)->where('record_id', $found['id'])->orderByDesc('acted_at')->orderByDesc('id')->limit(500)->get();
        $users = DB::table('users')->whereIn('id', [...$rows->pluck('acted_by')->filter(), ...$rows->pluck('on_behalf_of_user_id')->filter()])->pluck('name', 'id');
        $files = DB::table('files')->where('owner_type', 'status_history')->whereIn('owner_id', $rows->pluck('id'))->whereNull('deleted_at')->get(['uuid', 'original_name', 'size_bytes', 'mime_type', 'owner_id'])->groupBy('owner_id');
        $transitions = DB::table('transitions')->whereIn('id', $rows->pluck('transition_id')->filter())->pluck('uuid', 'id');
        $see = $access->allows($this->currentUser(), 'system.view_justifications');
        $j = $see ? $justifications->many($rows->pluck('justification_id')->filter()->map(static fn ($v) => (int) $v)->all()) : [];

        return response()->json(['data' => $rows->map(function ($h) use ($wf, $users, $files, $transitions, $j) {
            $t = $h->transition_id === null ? null : ($wf->transitions[strtolower((string) ($transitions[$h->transition_id] ?? ''))] ?? null);

            return [
                'from' => $this->engine->statusPayload($wf, $h->from_status_id === null ? null : (int) $h->from_status_id) ?? $this->archivedStatus($h->from_status_id),
                'to' => $this->engine->statusPayload($wf, (int) $h->to_status_id) ?? $this->archivedStatus($h->to_status_id),
                'transition' => $t === null ? null : ['key' => $t['key'], 'name' => WorkflowRuntime::label($t)],
                'source' => $h->source,
                'comment' => $h->comment,
                'attachments' => ($files[$h->id] ?? collect())->map(static fn ($f) => ['uuid' => strtolower((string) $f->uuid), 'name' => $f->original_name, 'size' => (int) $f->size_bytes, 'mime' => $f->mime_type])->values(),
                'by' => $h->acted_by === null ? null : ($users[$h->acted_by] ?? null),
                'on_behalf_of' => $h->on_behalf_of_user_id === null ? null : ($users[$h->on_behalf_of_user_id] ?? null),
                'at' => Carbon::parse($h->acted_at, 'UTC')->toIso8601ZuluString(),
                'seconds_in_previous' => $h->seconds_in_previous === null ? null : (int) $h->seconds_in_previous,
                'working_seconds_in_previous' => $h->working_seconds_in_previous === null ? null : (int) $h->working_seconds_in_previous,
                'justification' => $h->justification_id === null ? null : ($j[(int) $h->justification_id] ?? ['restricted' => true]),
            ];
        })->values()]);
    }

    /**
     * Transitions available only through a delegator's permission.
     *
     * @param  array<string, mixed>  $record
     * @return list<array<string, mixed>>
     */
    private function delegatedTransitions(FormRuntime $rt, array $record): array
    {
        $user = $this->currentUser();
        $own = array_column($this->engine->available($rt, $record, $user), 'uuid');
        $out = [];
        foreach (app(DelegationResolver::class)->principalsFor($user, $rt->form->id) as $p) {
            if ($p->id === $user->id) {
                continue;
            }
            foreach ($this->engine->available($rt, $record, $p) as $t) {
                if (! in_array($t['uuid'], $own, true)) {
                    $own[] = $t['uuid'];
                    $out[] = $t + ['on_behalf_of' => $p->name];
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function archivedStatus(mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $s = DB::table('statuses')->where('id', $id)->first(['uuid', 'key', 'color', 'icon']);

        return $s === null ? null : ['uuid' => strtolower((string) $s->uuid), 'key' => $s->key, 'name' => app(Translator::class)->get('status', (int) $id, 'name') ?? $s->key, 'color' => $s->color, 'icon' => $s->icon, 'archived' => true];
    }

    private function subjectName(string $type, int $id): string
    {
        return match ($type) {
            'user' => (string) DB::table('users')->where('id', $id)->value('name'),
            'role' => (string) (app(Translator::class)->get('role', $id, 'name') ?? DB::table('roles')->where('id', $id)->value('key')),
            default => (string) (app(Translator::class)->get('department', $id, 'name') ?? DB::table('departments')->where('id', $id)->value('code')),
        };
    }
}
