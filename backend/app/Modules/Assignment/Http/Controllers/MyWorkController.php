<?php

declare(strict_types=1);

namespace App\Modules\Assignment\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Assignment\Claims;
use App\Modules\Assignment\DelegationResolver;
use App\Modules\Assignment\Membership;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Views\RelationPaths;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * My Work (specification §4.25, architecture §19.4): the active assignments
 * of the user, their roles and department, and of the users they cover for,
 * across forms; each record filtered by the user's record scope; with due
 * date, SLA state, priority, claim and the columns chosen for the form's
 * queue.
 */
final class MyWorkController extends Controller
{
    private const CANDIDATES = 2000;

    public function __construct(
        private readonly Membership $members,
        private readonly DelegationResolver $delegations,
        private readonly FormRuntimes $runtimes,
        private readonly RecordScope $scope,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly RelationPaths $paths,
        private readonly Claims $claims,
        private readonly AccessResolver $access,
        private readonly Translator $translator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form' => ['sometimes', 'nullable', 'uuid'],
            'kind' => ['sometimes', Rule::in(['all', 'work', 'approval'])],
            'overdue' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $user = $this->user();
        $principals = $this->delegations->principalsFor($user, null);
        $subjects = [];
        foreach ($principals as $p) {
            foreach ($this->members->subjectsOf($p) as $type => $ids) {
                foreach ($ids as $id) {
                    $subjects[$type.':'.$id] ??= ['type' => $type, 'id' => $id, 'for' => $p->id === $user->id ? null : $p];
                }
            }
        }
        $q = DB::table('assignments')->where('status', 'active')->where(static function ($w) use ($subjects): void {
            foreach ($subjects as $s) {
                $w->orWhere(static fn ($x) => $x->where('assignee_type', $s['type'])->where('assignee_id', $s['id']));
            }
        });
        if (isset($data['form'])) {
            $q->where('form_id', (int) DB::table('forms')->where('uuid', strtolower($data['form']))->value('id'));
        }
        match ($data['kind'] ?? 'all') {
            'work' => $q->whereNull('approval_request_id'),
            'approval' => $q->whereNotNull('approval_request_id'),
            default => null,
        };
        if ($data['overdue'] ?? false) {
            $q->whereNotNull('due_at')->where('due_at', '<', Carbon::now('UTC')->format('Y-m-d H:i:s.u'));
        }
        $candidates = $q->orderByRaw('case when due_at is null then 1 else 0 end')->orderBy('due_at')->orderByDesc('priority')->orderBy('id')->limit(self::CANDIDATES)->get();

        // Keep the records the user (or a user they cover for) may see; one query per form.
        $visible = [];
        $runtimes = [];
        foreach ($candidates->groupBy('form_id') as $formId => $rows) {
            $form = Form::query()->find($formId);
            $rt = $form === null ? null : $this->runtimes->forForm($form);
            if ($rt === null || $form->state !== 'published') {
                continue;
            }
            $allowed = false;
            foreach ($principals as $p) {
                $allowed = $allowed || $this->access->allows($p, "form.{$form->uuid}.view");
            }
            if (! $allowed) {
                continue;
            }
            $runtimes[$formId] = $rt;
            $ids = $rows->pluck('record_id')->map(static fn ($v) => (int) $v)->unique()->values()->all();
            foreach ($this->scope->apply(DB::table($rt->table)->whereIn('id', $ids)->whereNull('deleted_at'), $rt, $user, 'view')->pluck('id') as $id) {
                $visible[$formId.':'.$id] = true;
            }
        }
        $items = $candidates->filter(static fn ($a) => isset($visible[$a->form_id.':'.$a->record_id]))->unique(static fn ($a) => $a->form_id.':'.$a->record_id.':'.($a->approval_request_id ?? 'w'))->values();
        $total = $items->count();
        $perPage = $data['per_page'] ?? 25;
        $page = $items->forPage($data['page'] ?? 1, $perPage)->values();

        $out = [];
        foreach ($page->groupBy('form_id') as $formId => $rows) {
            $rt = $runtimes[$formId];
            $ids = $rows->pluck('record_id')->map(static fn ($v) => (int) $v)->unique()->values()->all();
            $raw = DB::table($rt->table)->whereIn('id', $ids)->get()->map(static fn ($r) => (array) $r)->all();
            $records = [];
            foreach ($this->store->hydrate($rt, $raw, false) as $rec) {
                $records[$rec['id']] = $rec;
            }
            $columns = $this->columns($rt, $rows->first());
            $values = [];
            foreach ($columns as $c) {
                $values[implode('.', $c['path'])] = $this->paths->values($rt, $c['path'], $ids, $user);
            }
            $titles = [];
            foreach ($this->presenter->many($rt, array_values($records), [], false) as $p) {
                $titles[$p['uuid']] = $p['title'];
            }
            $timers = DB::table('sla_timers')->where('form_id', $formId)->whereIn('record_id', $ids)->whereIn('state', ['running', 'warned', 'breached'])
                ->orderBy('due_at')->get()->groupBy('record_id');
            $wf = WorkflowRuntime::for($rt);
            foreach ($rows as $a) {
                $rec = $records[(int) $a->record_id] ?? null;
                if ($rec === null) {
                    continue;
                }
                $timer = ($timers[$a->record_id] ?? collect())->first();
                $for = $subjects[$a->assignee_type.':'.$a->assignee_id]['for'] ?? null;
                $out[] = [
                    'assignment' => strtolower((string) $a->uuid),
                    'kind' => $a->approval_request_id === null ? 'work' : 'approval',
                    'approval' => $a->approval_request_id === null ? null : strtolower((string) DB::table('approval_requests')->where('id', $a->approval_request_id)->value('uuid')),
                    'form' => ['uuid' => $rt->form->uuid, 'key' => $rt->form->key, 'name' => $rt->form->translate('name') ?? Translator::humanize((string) $rt->form->key)],
                    'record' => ['uuid' => $rec['uuid'], 'title' => $titles[$rec['uuid']] ?? null, 'number' => $rec['system']['record_number'], 'row_version' => $rec['row_version']],
                    'status' => RecordPresenter::status($wf, $rec['system']['status_id'] ?? null),
                    'assignee' => ['type' => $a->assignee_type, 'name' => $this->subjectName($a->assignee_type, (int) $a->assignee_id)],
                    'on_behalf_of' => $for?->name,
                    'due_at' => $a->due_at === null ? null : Carbon::parse($a->due_at, 'UTC')->toIso8601ZuluString(),
                    'overdue' => $a->due_at !== null && Carbon::parse($a->due_at, 'UTC')->isPast(),
                    'priority' => (int) $a->priority,
                    'sla' => $timer === null ? null : ['state' => $timer->state, 'due_at' => Carbon::parse($timer->due_at, 'UTC')->toIso8601ZuluString()],
                    'claim' => $a->assignee_type === 'user' ? null : $this->claims->present($this->claims->holder((int) $formId, (int) $a->record_id)),
                    'claimable' => $a->assignee_type !== 'user' && $a->approval_request_id === null,
                    'columns' => array_map(static fn ($c) => ['path' => $c['path'], 'label' => $c['label'], 'value' => $values[implode('.', $c['path'])][(int) $a->record_id] ?? null], $columns),
                ];
            }
        }
        usort($out, static fn ($x, $y) => [$x['due_at'] === null ? 1 : 0, $x['due_at'], -$x['priority']] <=> [$y['due_at'] === null ? 1 : 0, $y['due_at'], -$y['priority']]);

        return response()->json(['data' => $out, 'meta' => ['total' => $total, 'page' => $data['page'] ?? 1, 'per_page' => $perPage, 'truncated' => $candidates->count() >= self::CANDIDATES]]);
    }

    /**
     * Columns of the queue that shows this form for the assignment's role or
     * department; otherwise none (title, status and dates are always shown).
     *
     * @return list<array{path: list<string>, label: string}>
     */
    private function columns(FormRuntime $rt, object $assignment): array
    {
        $q = DB::table('queue_forms')->join('queues', 'queues.id', '=', 'queue_forms.queue_id')->where('queue_forms.form_id', $rt->form->id)->where('queues.is_active', true);
        if (in_array($assignment->assignee_type, ['role', 'department'], true)) {
            $q->where('queues.type', $assignment->assignee_type)->where($assignment->assignee_type === 'role' ? 'queues.role_id' : 'queues.department_id', $assignment->assignee_id);
        }
        $raw = $q->orderBy('queue_forms.sort_order')->value('queue_forms.columns');
        $out = [];
        foreach (json_decode((string) $raw, true) ?: [] as $path) {
            $r = $this->paths->resolve($rt, $path, $this->user());
            if ($r === null) {
                continue;
            }
            $label = $r['field'] === null ? __('assignment.column_'.$r['system']) : ($this->translator->labelOf($r['field']['i18n']['label'] ?? null, end($path)));
            $out[] = ['path' => $path, 'label' => (string) $label];
        }

        return $out;
    }

    private function subjectName(string $type, int $id): string
    {
        return match ($type) {
            'user' => (string) DB::table('users')->where('id', $id)->value('name'),
            'role' => (string) ($this->translator->get('role', $id, 'name') ?? DB::table('roles')->where('id', $id)->value('key')),
            default => (string) ($this->translator->get('department', $id, 'name') ?? DB::table('departments')->where('id', $id)->value('code')),
        };
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
