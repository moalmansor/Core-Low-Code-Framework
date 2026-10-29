<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The work items a user sees in My Work (specification §4.25): active
 * assignments of the user, their roles and department, and of the people
 * they cover for, kept only where the record is visible to them. Shared by
 * the My Work list and the counts on menu badges.
 */
final class MyWork
{
    public const CANDIDATES = 2000;

    public function __construct(
        private readonly Membership $members,
        private readonly DelegationResolver $delegations,
        private readonly FormRuntimes $runtimes,
        private readonly RecordScope $scope,
        private readonly AccessResolver $access,
    ) {}

    /**
     * @param  array{form?: string|null, kind?: string, overdue?: bool}  $filters
     * @return array{candidates: Collection<int, \stdClass>, items: Collection<int, \stdClass>, runtimes: array<int|string, FormRuntime>, subjects: array<string, array{type: string, id: int, for: User|null}>}
     */
    public function visible(User $user, array $filters): array
    {
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
        if (isset($filters['form'])) {
            $q->where('form_id', (int) DB::table('forms')->where('uuid', strtolower($filters['form']))->value('id'));
        }
        match ($filters['kind'] ?? 'all') {
            'work' => $q->whereNull('approval_request_id'),
            'approval' => $q->whereNotNull('approval_request_id'),
            default => null,
        };
        if ($filters['overdue'] ?? false) {
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

        return ['candidates' => $candidates, 'items' => $items, 'runtimes' => $runtimes, 'subjects' => $subjects];
    }

    /** @param  'all'|'work'|'approval'  $kind */
    public function count(User $user, string $kind = 'all', ?string $form = null): int
    {
        return $this->visible($user, ['kind' => $kind, 'form' => $form])['items']->count();
    }
}
