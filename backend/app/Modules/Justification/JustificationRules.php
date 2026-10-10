<?php

declare(strict_types=1);

namespace App\Modules\Justification;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Access\AccessCache;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
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
 * Justification rules of a form as one document (specification §4.24,
 * architecture §14.10): per form, group, field, status reached, transition,
 * delete, restore and reassignment; for everyone, a role, a department or a
 * user; with an optional condition that switches the level. Rules take effect
 * when saved and are frozen into each published version for its history.
 * Scopes for actions, bulk operations, imports and merges arrive with those
 * features in Phase 4.
 */
final class JustificationRules
{
    public const SCOPES = ['form', 'group', 'field', 'status', 'transition', 'delete', 'restore', 'reassign'];

    public const LEVELS = ['not_required', 'optional', 'mandatory'];

    public function __construct(
        private readonly Translator $translator,
        private readonly OwnedConditions $conditions,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly AuditWriter $audit,
        private readonly AccessCache $cache,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('justification_rules')->where('form_id', $form->id)->orderBy('id')->get();
        $uuids = [
            'group' => DB::table('field_groups')->whereIn('id', $rows->pluck('group_id')->filter()->all())->pluck('uuid', 'id')->all(),
            'field' => DB::table('fields')->whereIn('id', $rows->pluck('field_id')->filter()->all())->pluck('uuid', 'id')->all(),
            'status' => DB::table('statuses')->whereIn('id', $rows->pluck('status_id')->filter()->all())->pluck('uuid', 'id')->all(),
            'transition' => DB::table('transitions')->whereIn('id', $rows->pluck('transition_id')->filter()->all())->pluck('uuid', 'id')->all(),
            'collection' => DB::table('forms')->whereIn('id', $rows->pluck('reason_code_collection_id')->filter()->all())->pluck('uuid', 'id')->all(),
        ];
        $subjects = [
            'role' => DB::table('roles')->whereIn('id', $rows->where('subject_type', 'role')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
            'user' => DB::table('users')->whereIn('id', $rows->where('subject_type', 'user')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
            'department' => DB::table('departments')->whereIn('id', $rows->where('subject_type', 'department')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
        ];
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());
        $tr = $this->translator->allMany('justification_rule', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $lower = static fn ($v) => $v === null ? null : strtolower((string) $v);

        return $rows->map(fn ($r): array => [
            'uuid' => strtolower((string) $r->uuid),
            'scope' => $r->scope,
            'target' => match ($r->scope) {
                'group' => $lower($uuids['group'][$r->group_id] ?? null),
                'field' => $lower($uuids['field'][$r->field_id] ?? null),
                'status' => $lower($uuids['status'][$r->status_id] ?? null),
                'transition' => $lower($uuids['transition'][$r->transition_id] ?? null),
                default => null,
            },
            'subject' => ['type' => $r->subject_type, 'uuid' => $r->subject_type === 'everyone' ? null : $lower($subjects[$r->subject_type][$r->subject_id] ?? null)],
            'level' => $r->level,
            'condition' => $r->condition_id === null ? null : ($asts[(int) $r->condition_id] ?? null),
            'levelWhen' => $r->level_when_condition,
            'text' => ['min' => $r->min_length === null ? null : (int) $r->min_length, 'max' => $r->max_length === null ? null : (int) $r->max_length],
            'reasonCodes' => ['mode' => $r->reason_code_mode, 'source' => $r->reason_code_source, 'set' => $r->reason_code_set, 'collection' => $lower($uuids['collection'][$r->reason_code_collection_id] ?? null)],
            'attachments' => ['mode' => $r->attachments_mode, 'max' => $r->max_attachments === null ? null : (int) $r->max_attachments, 'rules' => json_decode((string) ($r->attachment_rules ?? 'null'), true)],
            'showSummary' => (bool) $r->show_change_summary,
            'active' => (bool) $r->is_active,
            'i18n' => ['title' => (object) ($tr[(int) $r->id]['title'] ?? []), 'help' => (object) ($tr[(int) $r->id]['help'] ?? [])],
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /**
     * Rules frozen into a version (JSON-normalized).
     *
     * @return list<array<string, mixed>>
     */
    public function compile(Form $form): array
    {
        return json_decode((string) json_encode($this->load($form), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param  list<array<string, mixed>>  $rules */
    public function save(Form $form, array $rules, int $userId): void
    {
        $resolved = $this->validate($form, $rules);
        DB::transaction(function () use ($form, $rules, $resolved, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            $tr = [];
            foreach ($rules as $i => $r) {
                $ids = $resolved[$i];
                $existing = DB::table('justification_rules')->where('form_id', $form->id)->where('uuid', $r['uuid'])->first();
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'scope' => $r['scope'],
                    'group_id' => $ids['group'], 'field_id' => $ids['field'], 'status_id' => $ids['status'], 'transition_id' => $ids['transition'],
                    'subject_type' => $r['subject']['type'], 'subject_id' => $ids['subject'], 'level' => $r['level'],
                    'level_when_condition' => ($r['condition'] ?? null) === null ? null : ($r['levelWhen'] ?? null),
                    'min_length' => $r['text']['min'] ?? null, 'max_length' => $r['text']['max'] ?? null,
                    'reason_code_mode' => $r['reasonCodes']['mode'] ?? 'none', 'reason_code_source' => $r['reasonCodes']['source'] ?? 'codes',
                    'reason_code_set' => ($r['reasonCodes']['source'] ?? 'codes') === 'codes' ? ($r['reasonCodes']['set'] ?? null) : null,
                    'reason_code_collection_id' => $ids['collection'],
                    'attachments_mode' => $r['attachments']['mode'] ?? 'none', 'max_attachments' => $r['attachments']['max'] ?? null,
                    'attachment_rules' => isset($r['attachments']['rules']) ? json_encode($r['attachments']['rules']) : null,
                    'show_change_summary' => (bool) ($r['showSummary'] ?? false), 'is_active' => (bool) ($r['active'] ?? true),
                ];
                if ($existing === null) {
                    $id = (int) DB::table('justification_rules')->insertGetId($row + [
                        'uuid' => $r['uuid'], 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId, 'form_id' => $form->id, 'condition_id' => null,
                    ]);
                    $conditionId = null;
                } else {
                    $id = (int) $existing->id;
                    DB::table('justification_rules')->where('id', $id)->update($row);
                    $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
                }
                $newCondition = $this->conditions->put($form->id, 'justification_rule', $id, $r['condition'] ?? null, $conditionId);
                if ($newCondition !== $conditionId) {
                    DB::table('justification_rules')->where('id', $id)->update(['condition_id' => $newCondition]);
                }
                $tr[$id] = ['title' => (array) ($r['i18n']['title'] ?? []), 'help' => (array) ($r['i18n']['help'] ?? [])];
                $kept[] = $id;
            }
            $this->translator->syncObjects('justification_rule', $tr);
            foreach (DB::table('justification_rules')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->get(['id', 'condition_id']) as $gone) {
                DB::table('justification_rules')->where('id', $gone->id)->delete();
                $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
                $this->translator->forget('justification_rule', (int) $gone->id);
            }
            $this->audit->record('justification.rules_saved', 'config', null, 'form', $form->id, ['rules' => count($rules)], $userId);
            $this->cache->bump();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array{group: int|null, field: int|null, status: int|null, transition: int|null, subject: int|null, collection: int|null}>
     */
    private function validate(Form $form, array $rules): array
    {
        $errors = [];
        $out = [];
        $draft = $this->drafts->normalize($this->drafts->load($form));
        $resolver = $this->draftValidator->resolver($draft);
        $seen = [];
        $levels = ['none', 'optional', 'required'];
        foreach ($rules as $i => $r) {
            $p = "rules.{$i}";
            $ids = ['group' => null, 'field' => null, 'status' => null, 'transition' => null, 'subject' => null, 'collection' => null];
            if (! is_array($r) || ! is_string($r['uuid'] ?? null) || ! Str::isUuid($r['uuid']) || isset($seen[$r['uuid']])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);
                $out[] = $ids;

                continue;
            }
            $seen[$r['uuid']] = true;
            if (DB::table('justification_rules')->where('uuid', $r['uuid'])->where('form_id', '!=', $form->id)->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $scope = $r['scope'] ?? null;
            if (! in_array($scope, self::SCOPES, true)) {
                $errors["{$p}.scope"][] = __('validation.in', ['attribute' => 'scope']);
            }
            $target = $r['target'] ?? null;
            $table = match ($scope) {
                'group' => 'field_groups', 'field' => 'fields', 'status' => 'statuses', 'transition' => 'transitions', default => null,
            };
            if ($table !== null) {
                $id = is_string($target) ? DB::table($table)->where('form_id', $form->id)->where('uuid', strtolower($target))->whereNull('archived_at')->value('id') : null;
                if ($id === null) {
                    $errors["{$p}.target"][] = __('justification.unknown_target');
                } else {
                    $ids[match ($scope) {
                        'group' => 'group', 'field' => 'field', 'status' => 'status', default => 'transition',
                    }] = (int) $id;
                }
            } elseif ($target !== null) {
                $errors["{$p}.target"][] = __('justification.no_target');
            }
            $subjectType = $r['subject']['type'] ?? null;
            if (! in_array($subjectType, ['everyone', 'role', 'department', 'user'], true)) {
                $errors["{$p}.subject.type"][] = __('validation.in', ['attribute' => 'subject']);
            } elseif ($subjectType !== 'everyone') {
                $sid = DB::table(match ($subjectType) {
                    'role' => 'roles', 'department' => 'departments', default => 'users',
                })->where('uuid', (string) ($r['subject']['uuid'] ?? ''))->value('id');
                $sid === null ? $errors["{$p}.subject.uuid"][] = __('justification.unknown_subject') : $ids['subject'] = (int) $sid;
            }
            if (! in_array($r['level'] ?? null, self::LEVELS, true)) {
                $errors["{$p}.level"][] = __('validation.in', ['attribute' => 'level']);
            }
            $condition = $r['condition'] ?? null;
            if ($condition !== null) {
                try {
                    TypeChecker::check($condition, $resolver, 'boolean');
                } catch (StaticError $e) {
                    $errors["{$p}.condition"][] = $e->getMessage();
                }
                if (! in_array($r['levelWhen'] ?? null, self::LEVELS, true)) {
                    $errors["{$p}.levelWhen"][] = __('justification.level_when_required');
                }
            } elseif (($r['levelWhen'] ?? null) !== null) {
                $errors["{$p}.levelWhen"][] = __('justification.level_when_without_condition');
            }
            $min = $r['text']['min'] ?? null;
            $max = $r['text']['max'] ?? null;
            if (($min !== null && (! is_int($min) || $min < 0 || $min > 5000)) || ($max !== null && (! is_int($max) || $max < 1 || $max > 5000)) || ($min !== null && $max !== null && $min > $max)) {
                $errors["{$p}.text"][] = __('justification.invalid_length');
            }
            $codes = $r['reasonCodes'] ?? ['mode' => 'none'];
            if (! in_array($codes['mode'] ?? 'none', $levels, true) || ! in_array($codes['source'] ?? 'codes', ['codes', 'collection'], true)) {
                $errors["{$p}.reasonCodes"][] = __('validation.in', ['attribute' => 'reasonCodes']);
            } elseif (($codes['mode'] ?? 'none') !== 'none') {
                if (($codes['source'] ?? 'codes') === 'codes') {
                    if (! is_string($codes['set'] ?? null) || ! DB::table('justification_reason_codes')->where('set_key', $codes['set'])->where('is_active', true)->exists()) {
                        $errors["{$p}.reasonCodes.set"][] = __('justification.unknown_code_set');
                    }
                } else {
                    $cid = is_string($codes['collection'] ?? null) ? DB::table('forms')->where('uuid', strtolower($codes['collection']))->where('kind', 'collection')->value('id') : null;
                    $cid === null ? $errors["{$p}.reasonCodes.collection"][] = __('justification.unknown_collection') : $ids['collection'] = (int) $cid;
                }
            }
            $att = $r['attachments'] ?? ['mode' => 'none'];
            if (! in_array($att['mode'] ?? 'none', $levels, true)) {
                $errors["{$p}.attachments.mode"][] = __('validation.in', ['attribute' => 'attachments']);
            }
            if (isset($att['max']) && (! is_int($att['max']) || $att['max'] < 1 || $att['max'] > 20)) {
                $errors["{$p}.attachments.max"][] = __('justification.invalid_max_attachments');
            }
            if (isset($att['rules']) && (! is_array($att['rules']) || (isset($att['rules']['maxSizeKb']) && (! is_int($att['rules']['maxSizeKb']) || $att['rules']['maxSizeKb'] < 1)))) {
                $errors["{$p}.attachments.rules"][] = __('justification.invalid_attachment_rules');
            }
            foreach (['title', 'help'] as $k) {
                foreach ((array) ($r['i18n'][$k] ?? []) as $locale => $v) {
                    if ($v !== null && (! is_string($v) || mb_strlen($v) > ($k === 'title' ? 255 : 2000))) {
                        $errors["{$p}.i18n.{$k}.{$locale}"][] = __('validation.max.string', ['attribute' => $k, 'max' => $k === 'title' ? 255 : 2000]);
                    }
                }
            }
            $out[] = $ids;
        }
        if (count($rules) > 500) {
            $errors['rules'][] = __('justification.too_many');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
