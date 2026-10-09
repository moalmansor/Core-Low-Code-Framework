<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A form's assignment rules as one document (specification §4.25): for record
 * creation (`transition: null`) or a transition, in order, the first rule
 * whose condition holds assigns the record.
 */
final class AssignmentRules
{
    public function __construct(
        private readonly OwnedConditions $conditions,
        private readonly Membership $members,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly AuditWriter $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('assignment_rules')->where('form_id', $form->id)->orderBy('sort_order')->orderBy('id')->get();
        $transitions = DB::table('transitions')->whereIn('id', $rows->pluck('transition_id')->filter()->all())->pluck('uuid', 'id')->all();
        $fields = DB::table('fields')->whereIn('id', $rows->pluck('field_id')->filter()->all())->pluck('uuid', 'id')->all();
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());

        return $rows->map(fn ($r) => [
            'uuid' => strtolower((string) $r->uuid),
            'transition' => $r->transition_id === null ? null : strtolower((string) ($transitions[$r->transition_id] ?? '')),
            'strategy' => $r->strategy,
            'target' => $r->target_type === null ? null : ['type' => $r->target_type, 'uuid' => $this->members->uuidOf($r->target_type, (int) $r->target_id)],
            'field' => $r->field_id === null ? null : strtolower((string) ($fields[$r->field_id] ?? '')),
            'condition' => $r->condition_id === null ? null : ($asts[(int) $r->condition_id] ?? null),
            'dueInMinutes' => $r->due_in_minutes === null ? null : (int) $r->due_in_minutes,
            'workingTime' => (bool) $r->use_working_time,
            'priority' => (int) $r->priority,
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @param  list<array<string, mixed>>  $rules */
    public function save(Form $form, array $rules, int $userId): void
    {
        $resolved = $this->validate($form, $rules);
        DB::transaction(function () use ($form, $rules, $resolved, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            foreach ($rules as $i => $r) {
                $ids = $resolved[$i];
                $existing = DB::table('assignment_rules')->where('form_id', $form->id)->where('uuid', strtolower($r['uuid']))->first();
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'transition_id' => $ids['transition'], 'strategy' => $r['strategy'],
                    'target_type' => $ids['target_type'], 'target_id' => $ids['target'], 'field_id' => $ids['field'],
                    'due_in_minutes' => $r['dueInMinutes'] ?? null, 'use_working_time' => (bool) ($r['workingTime'] ?? false),
                    'priority' => (int) ($r['priority'] ?? 0), 'sort_order' => $i,
                ];
                if ($existing === null) {
                    $id = (int) DB::table('assignment_rules')->insertGetId($row + ['uuid' => strtolower($r['uuid']), 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId, 'form_id' => $form->id, 'condition_id' => null, 'round_robin_cursor_user_id' => null]);
                    $conditionId = null;
                } else {
                    $id = (int) $existing->id;
                    DB::table('assignment_rules')->where('id', $id)->update($row);
                    $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
                }
                $new = $this->conditions->put($form->id, 'assignment_rule', $id, $r['condition'] ?? null, $conditionId);
                if ($new !== $conditionId) {
                    DB::table('assignment_rules')->where('id', $id)->update(['condition_id' => $new]);
                }
                $kept[] = $id;
            }
            foreach (DB::table('assignment_rules')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->get(['id', 'condition_id']) as $gone) {
                // Assignments made by a removed rule keep their history; the link is cleared.
                DB::table('assignments')->where('assignment_rule_id', $gone->id)->update(['assignment_rule_id' => null]);
                DB::table('assignment_rules')->where('id', $gone->id)->delete();
                $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
            }
            $this->audit->record('assignment.rules_saved', 'config', null, 'form', $form->id, ['rules' => count($rules)], $userId);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array{transition: int|null, target_type: string|null, target: int|null, field: int|null}>
     */
    private function validate(Form $form, array $rules): array
    {
        $errors = [];
        $out = [];
        $draft = $this->drafts->normalize($this->drafts->load($form));
        $resolver = $this->draftValidator->resolver($draft);
        $seen = [];
        foreach ($rules as $i => $r) {
            $p = "rules.{$i}";
            $ids = ['transition' => null, 'target_type' => null, 'target' => null, 'field' => null];
            if (! is_array($r) || ! is_string($r['uuid'] ?? null) || ! Str::isUuid($r['uuid']) || isset($seen[strtolower($r['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);
                $out[] = $ids;

                continue;
            }
            $seen[strtolower($r['uuid'])] = true;
            if (DB::table('assignment_rules')->where('uuid', strtolower($r['uuid']))->where('form_id', '!=', $form->id)->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            if (($r['transition'] ?? null) !== null) {
                $tid = DB::table('transitions')->where('form_id', $form->id)->where('uuid', strtolower((string) $r['transition']))->whereNull('archived_at')->value('id');
                $tid === null ? $errors["{$p}.transition"][] = __('workflow.unknown_transition') : $ids['transition'] = (int) $tid;
            }
            $strategy = $r['strategy'] ?? null;
            if (! in_array($strategy, AssignmentService::STRATEGIES, true)) {
                $errors["{$p}.strategy"][] = __('validation.in', ['attribute' => 'strategy']);
            }
            $needsTarget = match ($strategy) {
                'user' => ['user'], 'role', 'round_robin', 'least_loaded' => ['role'], 'department' => ['department'], default => [],
            };
            if ($needsTarget !== []) {
                $type = $r['target']['type'] ?? null;
                $tid = in_array($type, $needsTarget, true) ? $this->members->idOf($type, isset($r['target']['uuid']) ? strtolower((string) $r['target']['uuid']) : null) : null;
                if ($tid === null) {
                    $errors["{$p}.target"][] = __('assignment.target_required');
                } else {
                    $ids['target_type'] = $type;
                    $ids['target'] = $tid;
                }
            }
            if ($strategy === 'field_user') {
                $field = null;
                foreach ($draft['fields'] as $f) {
                    if ($f['uuid'] === strtolower((string) ($r['field'] ?? '')) && $f['type'] === 'user') {
                        $field = DB::table('fields')->where('form_id', $form->id)->where('uuid', $f['uuid'])->value('id');
                    }
                }
                $field === null ? $errors["{$p}.field"][] = __('assignment.user_field_required') : $ids['field'] = (int) $field;
            }
            if (($r['condition'] ?? null) !== null) {
                try {
                    TypeChecker::check($r['condition'], $resolver, 'boolean');
                } catch (StaticError $e) {
                    $errors["{$p}.condition"][] = $e->getMessage();
                }
            }
            if (isset($r['dueInMinutes']) && (! is_int($r['dueInMinutes']) || $r['dueInMinutes'] < 1 || $r['dueInMinutes'] > 525600)) {
                $errors["{$p}.dueInMinutes"][] = __('workflow.invalid_minutes');
            }
            if (isset($r['priority']) && (! is_int($r['priority']) || $r['priority'] < -100 || $r['priority'] > 100)) {
                $errors["{$p}.priority"][] = __('assignment.invalid_priority');
            }
            $out[] = $ids;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
