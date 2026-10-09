<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Workflow\Models\SlaRule;
use App\Modules\Workflow\Models\Status;
use App\Modules\Workflow\Models\Transition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The workflow of a form as one document (specification §4.12, architecture
 * §14.1 `workflow`): statuses, transitions and SLA rules, addressed by uuid.
 * The working rows are the draft; `compile()` gives the section frozen into a
 * published version. A status, transition or SLA rule that a published
 * version used is archived when removed, never deleted, because records,
 * history and timers keep pointing at it (ADR-0031).
 */
final class WorkflowDocument
{
    public const LEVELS = ['none', 'optional', 'mandatory'];

    public const APPROVAL_MODES = ['none', 'all', 'any_n', 'quorum'];

    public const ESCALATIONS = ['notify', 'reassign', 'transition'];

    public function __construct(
        private readonly Translator $translator,
        private readonly OwnedConditions $conditions,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly ObjectPermissions $permissions,
        private readonly AuditWriter $audit,
        private readonly DatabaseDriver $driver,
    ) {}

    /** @return array{statuses: list<array<string, mixed>>, transitions: list<array<string, mixed>>, sla: list<array<string, mixed>>} */
    public function load(Form $form): array
    {
        $statuses = Status::query()->where('form_id', $form->id)->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get();
        $transitions = Transition::query()->where('form_id', $form->id)->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get();
        $sla = SlaRule::query()->where('form_id', $form->id)->whereNull('archived_at')->orderBy('id')->get();
        $statusUuid = Status::query()->where('form_id', $form->id)->pluck('uuid', 'id')->map(static fn ($u) => strtolower((string) $u))->all();
        $statusTr = $this->translator->allMany('status', $statuses->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $transitionTr = $this->translator->allMany('transition', $transitions->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $asts = $this->conditions->many([...$transitions->pluck('condition_id')->all(), ...$sla->pluck('condition_id')->all()]);
        $calendars = DB::table('business_calendars')->whereIn('id', $sla->pluck('business_calendar_id')->filter()->all())->pluck('uuid', 'id')->all();

        return [
            'statuses' => $statuses->map(static fn (Status $s): array => [
                'uuid' => strtolower($s->uuid),
                'key' => $s->key,
                'i18n' => ['name' => (object) ($statusTr[$s->id]['name'] ?? [])],
                'color' => $s->color,
                'icon' => $s->icon,
                'initial' => $s->is_initial,
                'final' => $s->is_final,
                'order' => $s->sort_order,
                'position' => $s->diagram_position,
            ])->values()->all(),
            'transitions' => $transitions->map(fn (Transition $t): array => [
                'uuid' => strtolower($t->uuid),
                'key' => $t->key,
                'from' => $t->from_status_id === null ? null : ($statusUuid[$t->from_status_id] ?? null),
                'to' => $statusUuid[$t->to_status_id] ?? null,
                'i18n' => ['name' => (object) ($transitionTr[$t->id]['name'] ?? [])],
                'condition' => $t->condition_id === null ? null : ($asts[$t->condition_id] ?? null),
                'requiredFields' => array_values($t->required_fields ?? []),
                'comment' => $t->comment_level,
                'attachments' => $t->attachments_level,
                'approval' => [
                    'mode' => $t->approval_mode,
                    'approvers' => array_values($t->approval_config['approvers'] ?? []),
                    'n' => $t->approval_config['n'] ?? null,
                    'quorumWeight' => $t->approval_config['quorumWeight'] ?? null,
                    'dueInMinutes' => $t->approval_config['dueInMinutes'] ?? null,
                    'rejection' => $t->rejection_behavior,
                    'rejectionStatus' => $t->rejection_status_id === null ? null : ($statusUuid[$t->rejection_status_id] ?? null),
                ],
                'confirmation' => $t->confirmation,
                'style' => $t->button_style,
                'order' => $t->sort_order,
                'edge' => $t->diagram_edge,
            ])->values()->all(),
            'sla' => $sla->map(fn (SlaRule $r): array => [
                'uuid' => strtolower($r->uuid),
                'status' => $statusUuid[$r->status_id] ?? null,
                'durationMinutes' => $r->duration_minutes,
                'workingTime' => $r->use_working_time,
                'calendar' => $r->business_calendar_id === null ? null : strtolower((string) ($calendars[$r->business_calendar_id] ?? '')),
                'warnBeforeMinutes' => $r->warn_before_minutes,
                'escalations' => array_values($r->escalations ?? []),
                'condition' => $r->condition_id === null ? null : ($asts[$r->condition_id] ?? null),
                'active' => $r->is_active,
            ])->values()->all(),
        ];
    }

    /** Optimistic-concurrency token of the stored document. */
    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /**
     * The section frozen into a version: the document with empty i18n objects
     * as arrays so equal workflows hash equally.
     *
     * @return array<string, mixed>
     */
    public function compile(Form $form): array
    {
        return json_decode((string) json_encode($this->load($form), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Problems that block publishing (the designer shows them while editing):
     * exactly one initial status, no transitions out of a final status, and
     * every status reachable from the initial one (unreachable ones are
     * warnings, they do not block).
     *
     * @param  array<string, mixed>  $doc
     * @return array{problems: list<array{path: string, code: string, message: string}>, warnings: list<array{path: string, code: string, message: string}>}
     */
    public function problems(array $doc): array
    {
        $problems = [];
        $warnings = [];
        $statuses = $doc['statuses'] ?? [];
        if ($statuses === []) {
            return ['problems' => [], 'warnings' => []];
        }
        $initial = array_values(array_filter($statuses, static fn ($s) => (bool) ($s['initial'] ?? false)));
        if (count($initial) !== 1) {
            $problems[] = ['path' => 'statuses', 'code' => 'initial_count', 'message' => __('workflow.initial_count')];
        }
        $final = [];
        foreach ($statuses as $s) {
            if ($s['final'] ?? false) {
                $final[$s['uuid']] = true;
            }
        }
        foreach ($doc['transitions'] ?? [] as $i => $t) {
            if ($t['from'] !== null && isset($final[$t['from']])) {
                $problems[] = ['path' => "transitions.{$i}.from", 'code' => 'final_outgoing', 'message' => __('workflow.final_outgoing', ['key' => $t['key']])];
            }
        }
        if ($initial !== []) {
            $reached = [];
            foreach ($initial as $s) {
                $reached[$s['uuid']] = true;
            }
            do {
                $grew = false;
                foreach ($doc['transitions'] ?? [] as $t) {
                    if (($t['from'] === null || isset($reached[$t['from']])) && ! isset($reached[$t['to']])) {
                        $reached[$t['to']] = true;
                        $grew = true;
                    }
                }
            } while ($grew);
            foreach ($statuses as $i => $s) {
                if (! isset($reached[$s['uuid']])) {
                    $warnings[] = ['path' => "statuses.{$i}", 'code' => 'unreachable', 'message' => __('workflow.unreachable', ['key' => $s['key']])];
                }
            }
        }

        return ['problems' => $problems, 'warnings' => $warnings];
    }

    /**
     * Validates and stores the document. Structural errors reject the save
     * (422); workflow problems are reported by `problems()` and only block
     * publishing, so the designer can save work in progress.
     *
     * @param  array<string, mixed>  $doc
     */
    public function save(Form $form, array $doc, int $userId): void
    {
        $doc = $this->restoreArchived($form, $doc);
        $this->validate($form, $doc);
        $published = $this->publishedUuids($form);
        DB::transaction(function () use ($form, $doc, $userId, $published): void {
            $before = $this->load($form);
            $now = Carbon::now('UTC');

            // Statuses.
            $statusIds = [];
            $statusTr = [];
            foreach ($doc['statuses'] as $i => $s) {
                $row = Status::query()->where('form_id', $form->id)->where('uuid', $s['uuid'])->first() ?? new Status;
                $row->fill([
                    'form_id' => $form->id, 'key' => $s['key'], 'color' => $s['color'], 'icon' => $s['icon'] ?? null,
                    'is_initial' => (bool) ($s['initial'] ?? false), 'is_final' => (bool) ($s['final'] ?? false),
                    'sort_order' => (int) ($s['order'] ?? $i), 'diagram_position' => $s['position'] ?? null, 'archived_at' => null,
                ]);
                $row->uuid = $s['uuid'];
                $row->save();
                $statusIds[$s['uuid']] = $row->id;
                $statusTr[$row->id] = ['name' => (array) ($s['i18n']['name'] ?? [])];
            }
            $this->translator->syncObjects('status', $statusTr);

            // Transitions.
            $transitionTr = [];
            $kept = [];
            foreach ($doc['transitions'] as $i => $t) {
                $row = Transition::query()->where('form_id', $form->id)->where('uuid', $t['uuid'])->first() ?? new Transition;
                $approval = $t['approval'] ?? [];
                $row->fill([
                    'form_id' => $form->id, 'key' => $t['key'],
                    'from_status_id' => $t['from'] === null ? null : $statusIds[$t['from']],
                    'to_status_id' => $statusIds[$t['to']],
                    'required_fields' => array_values($t['requiredFields'] ?? []),
                    'comment_level' => $t['comment'] ?? 'none',
                    'attachments_level' => $t['attachments'] ?? 'none',
                    'approval_mode' => $approval['mode'] ?? 'none',
                    'approval_config' => ($approval['mode'] ?? 'none') === 'none' ? null : [
                        'approvers' => array_values($approval['approvers'] ?? []),
                        'n' => $approval['n'] ?? null,
                        'quorumWeight' => $approval['quorumWeight'] ?? null,
                        'dueInMinutes' => $approval['dueInMinutes'] ?? null,
                    ],
                    'rejection_behavior' => $approval['rejection'] ?? 'immediate',
                    'rejection_status_id' => ($approval['rejectionStatus'] ?? null) === null ? null : $statusIds[$approval['rejectionStatus']],
                    'confirmation' => (bool) ($t['confirmation'] ?? false),
                    'button_style' => $t['style'] ?? null,
                    'sort_order' => (int) ($t['order'] ?? $i),
                    'diagram_edge' => $t['edge'] ?? null,
                    'archived_at' => null,
                ]);
                $row->uuid = $t['uuid'];
                $row->save();
                $conditionId = $this->conditions->put($form->id, 'transition', $row->id, $t['condition'] ?? null, $row->condition_id);
                if ($conditionId !== $row->condition_id) {
                    $row->forceFill(['condition_id' => $conditionId])->save();
                }
                $this->permissions->registerTransition($row->id, $row->uuid);
                $transitionTr[$row->id] = ['name' => (array) ($t['i18n']['name'] ?? [])];
                $kept[] = $row->id;
            }
            $this->translator->syncObjects('transition', $transitionTr);

            // SLA rules.
            $keptSla = [];
            foreach ($doc['sla'] as $r) {
                $row = SlaRule::query()->where('form_id', $form->id)->where('uuid', $r['uuid'])->first() ?? new SlaRule;
                $row->fill([
                    'form_id' => $form->id, 'status_id' => $statusIds[$r['status']],
                    'duration_minutes' => (int) $r['durationMinutes'], 'use_working_time' => (bool) ($r['workingTime'] ?? false),
                    'business_calendar_id' => ($r['calendar'] ?? null) === null ? null : DB::table('business_calendars')->where('uuid', $r['calendar'])->value('id'),
                    'warn_before_minutes' => $r['warnBeforeMinutes'] ?? null,
                    'escalations' => array_values($r['escalations'] ?? []),
                    'is_active' => (bool) ($r['active'] ?? true),
                    'archived_at' => null,
                ]);
                $row->uuid = $r['uuid'];
                $row->save();
                $conditionId = $this->conditions->put($form->id, 'sla_rule', $row->id, $r['condition'] ?? null, $row->condition_id);
                if ($conditionId !== $row->condition_id) {
                    $row->forceFill(['condition_id' => $conditionId])->save();
                }
                $keptSla[] = $row->id;
            }

            // Removals: archived when a published version used them, deleted otherwise.
            foreach (SlaRule::query()->where('form_id', $form->id)->whereNull('archived_at')->whereNotIn('id', $keptSla ?: [0])->get() as $gone) {
                if (isset($published['sla'][strtolower($gone->uuid)]) || DB::table('sla_timers')->where('sla_rule_id', $gone->id)->exists()) {
                    $gone->forceFill(['archived_at' => $now])->save();
                } else {
                    $this->conditions->forget($gone->condition_id);
                    $gone->delete();
                }
            }
            foreach (Transition::query()->where('form_id', $form->id)->whereNull('archived_at')->whereNotIn('id', $kept ?: [0])->get() as $gone) {
                if (isset($published['transitions'][strtolower($gone->uuid)]) || $this->transitionReferenced($gone->id)) {
                    $gone->forceFill(['archived_at' => $now])->save();
                } else {
                    DB::table('assignment_rules')->where('transition_id', $gone->id)->delete();
                    DB::table('justification_rules')->where('transition_id', $gone->id)->delete();
                    $this->conditions->forget($gone->condition_id);
                    $this->permissions->forget('transition.'.strtolower($gone->uuid));
                    $this->translator->forget('transition', $gone->id);
                    $gone->delete();
                }
            }
            foreach (Status::query()->where('form_id', $form->id)->whereNull('archived_at')->whereNotIn('id', array_values($statusIds) ?: [0])->get() as $gone) {
                if (isset($published['statuses'][strtolower($gone->uuid)]) || $this->statusReferenced($form, $gone->id)) {
                    $gone->forceFill(['archived_at' => $now, 'is_initial' => false])->save();
                } else {
                    DB::table('field_access_rules')->where('status_id', $gone->id)->delete();
                    DB::table('justification_rules')->where('status_id', $gone->id)->delete();
                    DB::table('status_mappings')->where('to_status_id', $gone->id)->whereNull('applied_at')->delete();
                    $this->translator->forget('status', $gone->id);
                    $gone->delete();
                }
            }
            $form->forceFill(['draft_updated_at' => $now, 'draft_updated_by' => $userId])->saveQuietly();
            $after = $this->load($form);
            if (DefinitionCompiler::hash($before) !== DefinitionCompiler::hash($after)) {
                $this->audit->record('workflow.saved', 'config', null, 'form', $form->id, [
                    'statuses' => count($after['statuses']), 'transitions' => count($after['transitions']), 'sla' => count($after['sla']),
                ], $userId);
            }
        });
    }

    /**
     * A status or transition added with the key of one the document no
     * longer contains (removed now or archived earlier) is that same one: the
     * new uuid is replaced by the existing one throughout the document, so
     * records, history and timers keep pointing at the same row.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function restoreArchived(Form $form, array $doc): array
    {
        $map = [];
        foreach (['statuses' => Status::class, 'transitions' => Transition::class] as $section => $model) {
            $live = $model::query()->where('form_id', $form->id)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
            // Rows the document drops (archived earlier, or being removed now) can be taken over by key.
            $archived = $model::query()->where('form_id', $form->id)->pluck('uuid', 'key')->map(static fn ($u) => strtolower((string) $u))->all();
            $inDoc = array_column(array_filter($doc[$section] ?? [], 'is_array'), 'uuid');
            foreach ($doc[$section] ?? [] as $o) {
                if (! is_array($o) || ! is_string($o['uuid'] ?? null) || ! is_string($o['key'] ?? null) || in_array($o['uuid'], $live, true)) {
                    continue;
                }
                $old = $archived[$o['key']] ?? null;
                if ($old !== null && ! in_array($old, $inDoc, true)) {
                    $map[$o['uuid']] = $old;
                }
            }
        }
        if ($map === []) {
            return $doc;
        }
        $walk = static function (mixed $v) use (&$walk, $map): mixed {
            if (is_string($v)) {
                return $map[$v] ?? $v;
            }
            if (is_array($v)) {
                return array_map($walk, $v);
            }

            return $v;
        };

        return $walk($doc);
    }

    /**
     * Uuids of statuses, transitions and SLA rules used by any published
     * version of the form.
     *
     * @return array{statuses: array<string, true>, transitions: array<string, true>, sla: array<string, true>}
     */
    public function publishedUuids(Form $form): array
    {
        $out = ['statuses' => [], 'transitions' => [], 'sla' => []];
        foreach (DB::table('form_versions')->where('form_id', $form->id)->pluck('definition') as $raw) {
            $wf = (json_decode((string) $raw, true) ?: [])['workflow'] ?? [];
            foreach (['statuses', 'transitions', 'sla'] as $k) {
                foreach ($wf[$k] ?? [] as $o) {
                    $out[$k][strtolower((string) $o['uuid'])] = true;
                }
            }
        }

        return $out;
    }

    private function transitionReferenced(int $id): bool
    {
        return DB::table('status_history')->where('transition_id', $id)->exists()
            || DB::table('approval_requests')->where('transition_id', $id)->exists()
            || DB::table('assignments')->where('transition_id', $id)->exists();
    }

    private function statusReferenced(Form $form, int $id): bool
    {
        if (DB::table('status_history')->where(static fn ($q) => $q->where('to_status_id', $id)->orWhere('from_status_id', $id))->exists()
            || DB::table('approval_requests')->where('rejection_status_id', $id)->exists()
            || DB::table('status_mappings')->where('to_status_id', $id)->whereNotNull('applied_at')->exists()) {
            return true;
        }

        return $form->current_version_id !== null && in_array(strtolower($form->table_name), array_map('strtolower', $this->driver->tables()), true)
            && DB::table($form->table_name)->where('status_id', $id)->exists();
    }

    /**
     * Structural validation: shape, unique keys, references between statuses,
     * transitions and rules, fields, approvers and calendars, and the
     * expressions' types against the form's draft.
     *
     * @param  array<string, mixed>  $doc
     */
    private function validate(Form $form, array $doc): void
    {
        $errors = [];
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        $key = '/^[a-z][a-z0-9_]{0,47}$/';
        foreach (['statuses', 'transitions', 'sla'] as $section) {
            if (! isset($doc[$section]) || ! is_array($doc[$section]) || ! array_is_list($doc[$section])) {
                throw ValidationException::withMessages([$section => __('validation.array', ['attribute' => $section])]);
            }
        }
        if (count($doc['statuses']) > 100 || count($doc['transitions']) > 500 || count($doc['sla']) > 200) {
            throw ValidationException::withMessages(['statuses' => __('workflow.too_large')]);
        }
        $statusUuids = [];
        $keys = [];
        $seen = [];
        $own = Status::query()->where('form_id', $form->id)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        foreach ($doc['statuses'] as $i => $s) {
            $p = "statuses.{$i}";
            if (! is_array($s) || ! is_string($s['uuid'] ?? null) || preg_match($uuid, $s['uuid']) !== 1) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            if (isset($seen[$s['uuid']]) || (! in_array($s['uuid'], $own, true) && Status::query()->where('uuid', $s['uuid'])->exists())) {
                $errors["{$p}.uuid"][] = __('workflow.duplicate_uuid');
            }
            $seen[$s['uuid']] = true;
            if (! is_string($s['key'] ?? null) || preg_match($key, $s['key']) !== 1) {
                $errors["{$p}.key"][] = __('workflow.invalid_key');
            } elseif (isset($keys[$s['key']])) {
                $errors["{$p}.key"][] = __('workflow.duplicate_key', ['key' => $s['key']]);
            } elseif (Status::query()->where('form_id', $form->id)->where('key', $s['key'])->where('uuid', '!=', $s['uuid'])->exists()) {
                $errors["{$p}.key"][] = __('workflow.key_of_removed_status', ['key' => $s['key']]);
            }
            $keys[$s['key'] ?? ''] = true;
            if (! is_string($s['color'] ?? null) || preg_match('/^#[0-9a-fA-F]{6}$/', $s['color']) !== 1) {
                $errors["{$p}.color"][] = __('workflow.invalid_color');
            }
            if (isset($s['icon']) && (! is_string($s['icon']) || mb_strlen($s['icon']) > 64)) {
                $errors["{$p}.icon"][] = __('validation.max.string', ['attribute' => 'icon', 'max' => 64]);
            }
            $errors += $this->i18nErrors($s['i18n']['name'] ?? null, "{$p}.i18n.name", true);
            if (isset($s['position']) && (! is_array($s['position']) || ! is_numeric($s['position']['x'] ?? null) || ! is_numeric($s['position']['y'] ?? null))) {
                $errors["{$p}.position"][] = __('workflow.invalid_position');
            }
            $statusUuids[$s['uuid']] = true;
        }

        $draft = $this->drafts->normalize($this->drafts->load($form));
        $fieldUuids = array_flip(array_column($draft['fields'], 'uuid'));
        $resolver = $this->draftValidator->resolver($draft);
        $checkAst = function (mixed $ast, string $path) use (&$errors, $resolver): void {
            if ($ast === null) {
                return;
            }
            if (! is_array($ast)) {
                $errors[$path][] = __('workflow.invalid_condition');

                return;
            }
            try {
                TypeChecker::check($ast, $resolver, 'boolean');
            } catch (StaticError $e) {
                $errors[$path][] = $e->getMessage();
            }
        };

        $keys = [];
        $seen = [];
        $ownTransitions = Transition::query()->where('form_id', $form->id)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        foreach ($doc['transitions'] as $i => $t) {
            $p = "transitions.{$i}";
            if (! is_array($t) || ! is_string($t['uuid'] ?? null) || preg_match($uuid, $t['uuid']) !== 1) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            if (isset($seen[$t['uuid']]) || (! in_array($t['uuid'], $ownTransitions, true) && Transition::query()->where('uuid', $t['uuid'])->exists())) {
                $errors["{$p}.uuid"][] = __('workflow.duplicate_uuid');
            }
            $seen[$t['uuid']] = true;
            if (! is_string($t['key'] ?? null) || preg_match($key, $t['key']) !== 1) {
                $errors["{$p}.key"][] = __('workflow.invalid_key');
            } elseif (isset($keys[$t['key']])) {
                $errors["{$p}.key"][] = __('workflow.duplicate_key', ['key' => $t['key']]);
            } elseif (Transition::query()->where('form_id', $form->id)->where('key', $t['key'])->where('uuid', '!=', $t['uuid'])->exists()) {
                $errors["{$p}.key"][] = __('workflow.key_of_removed_transition', ['key' => $t['key']]);
            }
            $keys[$t['key'] ?? ''] = true;
            if (! array_key_exists('from', $t) || ($t['from'] !== null && ! isset($statusUuids[$t['from']]))) {
                $errors["{$p}.from"][] = __('workflow.unknown_status');
            }
            if (! isset($statusUuids[$t['to'] ?? ''])) {
                $errors["{$p}.to"][] = __('workflow.unknown_status');
            }
            if (($t['from'] ?? null) !== null && ($t['from'] ?? null) === ($t['to'] ?? null)) {
                $errors["{$p}.to"][] = __('workflow.same_status');
            }
            $errors += $this->i18nErrors($t['i18n']['name'] ?? null, "{$p}.i18n.name", true);
            $checkAst($t['condition'] ?? null, "{$p}.condition");
            foreach ((array) ($t['requiredFields'] ?? []) as $j => $f) {
                if (! is_string($f) || ! isset($fieldUuids[$f])) {
                    $errors["{$p}.requiredFields.{$j}"][] = __('workflow.unknown_field');
                }
            }
            foreach (['comment', 'attachments'] as $level) {
                if (! in_array($t[$level] ?? 'none', self::LEVELS, true)) {
                    $errors["{$p}.{$level}"][] = __('validation.in', ['attribute' => $level]);
                }
            }
            $a = $t['approval'] ?? ['mode' => 'none'];
            $mode = $a['mode'] ?? 'none';
            if (! in_array($mode, self::APPROVAL_MODES, true)) {
                $errors["{$p}.approval.mode"][] = __('validation.in', ['attribute' => 'mode']);
            } elseif ($mode !== 'none') {
                $approvers = $a['approvers'] ?? [];
                if (! is_array($approvers) || $approvers === [] || count($approvers) > 50) {
                    $errors["{$p}.approval.approvers"][] = __('workflow.approvers_required');
                    $approvers = [];
                }
                $totalWeight = 0.0;
                $pairs = [];
                foreach ($approvers as $j => $ap) {
                    $type = $ap['type'] ?? null;
                    $table = match ($type) {
                        'user' => 'users', 'role' => 'roles', 'department' => 'departments', default => null,
                    };
                    if ($table === null || ! is_string($ap['uuid'] ?? null) || ! DB::table($table)->where('uuid', $ap['uuid'])->exists()) {
                        $errors["{$p}.approval.approvers.{$j}"][] = __('workflow.unknown_approver');
                    } elseif (isset($pairs[$type.':'.$ap['uuid']])) {
                        $errors["{$p}.approval.approvers.{$j}"][] = __('workflow.duplicate_approver');
                    }
                    $pairs[($type ?? '').':'.($ap['uuid'] ?? '')] = true;
                    $weight = $ap['weight'] ?? 1;
                    if (! is_numeric($weight) || (float) $weight <= 0 || (float) $weight > 9999999) {
                        $errors["{$p}.approval.approvers.{$j}.weight"][] = __('workflow.invalid_weight');
                    } else {
                        $totalWeight += (float) $weight;
                    }
                }
                if ($mode === 'any_n' && (! is_int($a['n'] ?? null) || $a['n'] < 1 || $a['n'] > count($approvers))) {
                    $errors["{$p}.approval.n"][] = __('workflow.invalid_n', ['max' => count($approvers)]);
                }
                if ($mode === 'quorum' && (! is_numeric($a['quorumWeight'] ?? null) || (float) $a['quorumWeight'] <= 0 || (float) $a['quorumWeight'] > $totalWeight)) {
                    $errors["{$p}.approval.quorumWeight"][] = __('workflow.invalid_quorum', ['max' => $totalWeight]);
                }
                if (! in_array($a['rejection'] ?? 'immediate', ['immediate', 'wait_all'], true)) {
                    $errors["{$p}.approval.rejection"][] = __('validation.in', ['attribute' => 'rejection']);
                }
                if (($a['rejectionStatus'] ?? null) !== null && ! isset($statusUuids[$a['rejectionStatus']])) {
                    $errors["{$p}.approval.rejectionStatus"][] = __('workflow.unknown_status');
                }
                if (isset($a['dueInMinutes']) && (! is_int($a['dueInMinutes']) || $a['dueInMinutes'] < 1 || $a['dueInMinutes'] > 525600)) {
                    $errors["{$p}.approval.dueInMinutes"][] = __('workflow.invalid_minutes');
                }
            }
            if (isset($t['style']) && ! is_array($t['style'])) {
                $errors["{$p}.style"][] = __('validation.array', ['attribute' => 'style']);
            }
        }

        $transitionUuids = array_flip(array_column(array_filter($doc['transitions'], 'is_array'), 'uuid'));
        $seen = [];
        $ownSla = SlaRule::query()->where('form_id', $form->id)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        foreach ($doc['sla'] as $i => $r) {
            $p = "sla.{$i}";
            if (! is_array($r) || ! is_string($r['uuid'] ?? null) || preg_match($uuid, $r['uuid']) !== 1) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            if (isset($seen[$r['uuid']]) || (! in_array($r['uuid'], $ownSla, true) && SlaRule::query()->where('uuid', $r['uuid'])->exists())) {
                $errors["{$p}.uuid"][] = __('workflow.duplicate_uuid');
            }
            $seen[$r['uuid']] = true;
            if (! isset($statusUuids[$r['status'] ?? ''])) {
                $errors["{$p}.status"][] = __('workflow.unknown_status');
            }
            if (! is_int($r['durationMinutes'] ?? null) || $r['durationMinutes'] < 1 || $r['durationMinutes'] > 5256000) {
                $errors["{$p}.durationMinutes"][] = __('workflow.invalid_minutes');
            }
            if (isset($r['warnBeforeMinutes']) && (! is_int($r['warnBeforeMinutes']) || $r['warnBeforeMinutes'] < 1 || $r['warnBeforeMinutes'] >= ($r['durationMinutes'] ?? 0))) {
                $errors["{$p}.warnBeforeMinutes"][] = __('workflow.invalid_warning');
            }
            if (($r['calendar'] ?? null) !== null && ! DB::table('business_calendars')->where('uuid', $r['calendar'])->exists()) {
                $errors["{$p}.calendar"][] = __('workflow.unknown_calendar');
            }
            $checkAst($r['condition'] ?? null, "{$p}.condition");
            foreach ((array) ($r['escalations'] ?? []) as $j => $e) {
                $ep = "{$p}.escalations.{$j}";
                if (! is_array($e) || ! in_array($e['action'] ?? null, self::ESCALATIONS, true)) {
                    $errors["{$ep}.action"][] = __('validation.in', ['attribute' => 'action']);

                    continue;
                }
                if (! is_int($e['afterMinutes'] ?? null) || $e['afterMinutes'] < 0 || $e['afterMinutes'] > 5256000) {
                    $errors["{$ep}.afterMinutes"][] = __('workflow.invalid_minutes');
                }
                $params = $e['params'] ?? [];
                if ($e['action'] === 'transition' && ! isset($transitionUuids[$params['transition'] ?? ''])) {
                    $errors["{$ep}.params.transition"][] = __('workflow.unknown_transition');
                }
                if ($e['action'] === 'reassign' || $e['action'] === 'notify') {
                    $targets = $e['action'] === 'reassign' ? [$params['to'] ?? null] : ($params['to'] ?? []);
                    if (! is_array($targets) || $targets === []) {
                        $errors["{$ep}.params.to"][] = __('workflow.escalation_target_required');
                    }
                    foreach (is_array($targets) ? $targets : [] as $k => $to) {
                        $type = $to['type'] ?? null;
                        $allowed = $e['action'] === 'notify' ? ['user', 'role', 'department', 'assignee', 'owner'] : ['user', 'role', 'department'];
                        $table = match ($type) {
                            'user' => 'users', 'role' => 'roles', 'department' => 'departments', default => null,
                        };
                        if (! in_array($type, $allowed, true) || ($table !== null && (! is_string($to['uuid'] ?? null) || ! DB::table($table)->where('uuid', $to['uuid'])->exists()))) {
                            $errors["{$ep}.params.to.{$k}"][] = __('workflow.unknown_approver');
                        }
                    }
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, list<string>> */
    private function i18nErrors(mixed $values, string $path, bool $required): array
    {
        $values = is_object($values) ? (array) $values : $values;
        if (! is_array($values)) {
            return $required ? [$path => [__('workflow.name_required')]] : [];
        }
        $default = $this->translator->defaultLocale();
        if ($required && trim((string) ($values[$default] ?? '')) === '') {
            return [$path => [__('workflow.name_required')]];
        }
        foreach ($values as $locale => $v) {
            if ($v !== null && (! is_string($v) || mb_strlen($v) > 255)) {
                return ["{$path}.{$locale}" => [__('validation.max.string', ['attribute' => 'name', 'max' => 255])]];
            }
        }

        return [];
    }
}
