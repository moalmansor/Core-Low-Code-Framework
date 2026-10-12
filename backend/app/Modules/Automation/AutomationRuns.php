<?php

declare(strict_types=1);

namespace App\Modules\Automation;

use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The run history of automations (specification §4.31): what triggered
 * each run, how many records it covered and changed, each step's outcome
 * with its duration and error, and how the run ended.
 */
final class AutomationRuns
{
    public function __construct(private readonly FormRuntimes $runtimes) {}

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function list(object $automation, int $page, ?string $status): array
    {
        $q = DB::table('automation_runs')->where('automation_id', $automation->id)->when($status !== null, static fn ($q) => $q->where('status', $status));
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage(max(1, $page), 25)->get();

        return ['rows' => $rows->map(fn ($r) => $this->summary($r))->values()->all(), 'total' => $total];
    }

    /** @return array<string, mixed> */
    public function summary(object $r): array
    {
        $ref = json_decode((string) $r->trigger_ref, true) ?: [];
        $by = $r->triggered_by === null ? null : DB::table('users')->where('id', $r->triggered_by)->value('name');

        return [
            'uuid' => strtolower((string) $r->uuid),
            'status' => $r->status,
            'trigger' => $r->trigger_type,
            'test' => (bool) $r->is_test,
            'depth' => (int) $r->chain_depth,
            'records' => count((array) ($ref['records'] ?? [])),
            'affected' => (int) $r->records_affected,
            'by' => $by === null ? null : (string) $by,
            'created_at' => $this->iso($r->created_at),
            'started_at' => $this->iso($r->started_at),
            'finished_at' => $this->iso($r->finished_at),
            'resume_at' => $this->iso($r->resume_at),
            'duration_ms' => $r->duration_ms === null ? null : (int) $r->duration_ms,
            'error' => $r->error,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(object $r): array
    {
        $ref = json_decode((string) $r->trigger_ref, true) ?: [];
        $ids = array_slice(array_map('intval', (array) ($ref['records'] ?? [])), 0, 50);
        $a = DB::table('automations')->where('id', $r->automation_id)->first();
        $form = $a === null || $a->form_id === null ? null : Form::query()->find($a->form_id);
        $table = $form === null ? null : $this->runtimes->forForm($form)?->table;
        $records = [];
        if ($table !== null && $ids !== []) {
            $records = DB::table((string) $table)->whereIn('id', $ids)->orderBy('id')->get(['uuid', 'record_number'])
                ->map(static fn ($x) => ['uuid' => strtolower((string) $x->uuid), 'number' => $x->record_number])->values()->all();
        }
        $parent = $r->parent_run_id === null ? null : DB::table('automation_runs')->where('id', $r->parent_run_id)->first(['uuid', 'automation_id']);
        $parentAutomation = $parent === null ? null : DB::table('automations')->where('id', $parent->automation_id)->value('uuid');
        $log = json_decode((string) $r->steps_log, true);

        return $this->summary($r) + [
            'automation' => $a === null ? null : strtolower((string) $a->uuid),
            'form' => $form === null ? null : strtolower((string) $form->uuid),
            'trigger_ref' => array_intersect_key($ref, array_flip(['file', 'due', 'at'])),
            'record_list' => $records,
            'parent' => $parent === null ? null : ['uuid' => strtolower((string) $parent->uuid), 'automation' => $parentAutomation === null ? null : strtolower((string) $parentAutomation)],
            'log' => is_array($log) ? array_values($log) : [],
        ];
    }

    private function iso(mixed $at): ?string
    {
        return $at === null ? null : Carbon::parse((string) $at, 'UTC')->toIso8601ZuluString();
    }
}
