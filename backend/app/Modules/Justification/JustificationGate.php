<?php

declare(strict_types=1);

namespace App\Modules\Justification;

use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\Models\Justification;
use App\Modules\Justification\Models\ReasonCode;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\References;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The justification gate (specification §4.24, architecture §19.3), pipeline
 * step 9: after validation passed, it selects the rules that match the change
 * (form, groups and fields changed, the status the record is in, the
 * transition, delete, restore, reassignment) and the actor (the most specific
 * subject tier per scope and target wins), evaluates their conditions, and
 * takes the strictest requirement with merged constraints. A mandatory
 * requirement without a justification answers 422 `justification_required`
 * with the prompt; a justification given is validated and saved, immutable,
 * in the same transaction as the change.
 *
 * The per-field and per-group "Change justification" setting of the form
 * builder counts as a rule for everyone on that field or group.
 */
final class JustificationGate
{
    private const RANK = ['not_required' => 0, 'optional' => 1, 'mandatory' => 2];

    private const MODE_RANK = ['none' => 0, 'optional' => 1, 'required' => 2];

    private const TIER = ['everyone' => 0, 'department' => 1, 'role' => 2, 'user' => 3];

    public function __construct(
        private readonly OwnedConditions $conditions,
        private readonly RuleRuntime $rules,
        private readonly Translator $translator,
        private readonly FormRuntimes $runtimes,
        private readonly References $references,
    ) {}

    /**
     * The merged requirement for a change, or null when no justification is
     * asked for.
     *
     * @param  array{fields?: list<string>, status?: int|null, transition?: int|null, values?: array<string, mixed>, old?: array<string, mixed>|null}  $change
     * @return array<string, mixed>|null
     */
    public function requirement(FormRuntime $rt, User $user, string $context, array $change): ?array
    {
        $matched = $this->matching($rt, $user, $context, $change);
        if ($matched === []) {
            return null;
        }
        $req = ['level' => 'not_required', 'min' => null, 'max' => null, 'codes' => 'none', 'sets' => [], 'collections' => [], 'attachments' => 'none', 'maxAttachments' => null, 'attachmentRules' => [], 'summary' => false, 'rules' => [], 'title' => null, 'help' => null];
        foreach ($matched as $r) {
            $level = $r['level'];
            if (($r['condition'] ?? null) !== null && $this->rules->holds($rt, $r['condition'], $change['values'] ?? [], $user, 'edit', $change['old'] ?? null)) {
                $level = $r['levelWhen'] ?? $level;
            }
            if ($level === 'not_required') {
                continue;
            }
            $req['rules'][] = $r['uuid'];
            if (self::RANK[$level] > self::RANK[$req['level']]) {
                $req['level'] = $level;
                $req['title'] = $r['title'] ?? $req['title'];
                $req['help'] = $r['help'] ?? $req['help'];
            }
            $req['title'] ??= $r['title'] ?? null;
            $req['help'] ??= $r['help'] ?? null;
            if (($r['text']['min'] ?? null) !== null) {
                $req['min'] = max($req['min'] ?? 0, (int) $r['text']['min']);
            }
            if (($r['text']['max'] ?? null) !== null) {
                $req['max'] = min($req['max'] ?? PHP_INT_MAX, (int) $r['text']['max']);
            }
            $mode = $r['reasonCodes']['mode'] ?? 'none';
            if ($mode !== 'none') {
                $req['codes'] = self::MODE_RANK[$mode] > self::MODE_RANK[$req['codes']] ? $mode : $req['codes'];
                if (($r['reasonCodes']['source'] ?? 'codes') === 'codes' && ($r['reasonCodes']['set'] ?? null) !== null) {
                    $req['sets'][$r['reasonCodes']['set']] = true;
                } elseif (($r['reasonCodes']['collection'] ?? null) !== null) {
                    $req['collections'][$r['reasonCodes']['collection']] = true;
                }
            }
            $att = $r['attachments']['mode'] ?? 'none';
            if ($att !== 'none') {
                $req['attachments'] = self::MODE_RANK[$att] > self::MODE_RANK[$req['attachments']] ? $att : $req['attachments'];
                if (($r['attachments']['max'] ?? null) !== null) {
                    $req['maxAttachments'] = min($req['maxAttachments'] ?? PHP_INT_MAX, (int) $r['attachments']['max']);
                }
                $req['attachmentRules'] = $this->mergeFileRules($req['attachmentRules'], (array) ($r['attachments']['rules'] ?? []));
            }
            $req['summary'] = $req['summary'] || ($r['showSummary'] ?? false);
        }
        if ($req['level'] === 'not_required') {
            return null;
        }
        if ($req['min'] !== null && $req['max'] !== null && $req['min'] > $req['max']) {
            $req['min'] = $req['max'];
        }

        return $req;
    }

    /**
     * Enforces the requirement: 422 `justification_required` with the prompt
     * when a mandatory justification is missing, 422 `justification_invalid`
     * when the one given breaks the constraints. Returns what to save (null
     * when nothing is saved).
     *
     * @param  array<string, mixed>  $change
     * @param  array<string, mixed>|null  $payload  {reason_text, reason_code, note, attachments[]}
     * @return array<string, mixed>|null
     */
    public function enforce(FormRuntime $rt, User $user, string $context, array $change, ?array $payload): ?array
    {
        $req = $this->requirement($rt, $user, $context, $change);
        $given = $payload !== null && (trim((string) ($payload['reason_text'] ?? '')) !== '' || ($payload['reason_code'] ?? null) !== null || ($payload['attachments'] ?? []) !== []);
        if ($req === null) {
            return null;
        }
        if (! $given) {
            if ($req['level'] === 'mandatory') {
                throw new RecordException(422, 'justification_required', __('justification.required'), ['justification' => $this->prompt($rt, $req, $change, $user)]);
            }

            return null;
        }
        $errors = [];
        $text = trim((string) ($payload['reason_text'] ?? ''));
        $min = max($req['min'] ?? 0, $req['level'] === 'mandatory' && $req['codes'] !== 'required' ? 1 : 0);
        if (mb_strlen($text) < $min) {
            $errors['reason_text'][] = $text === '' ? __('justification.text_required') : __('justification.text_too_short', ['min' => $min]);
        }
        if (mb_strlen($text) > ($req['max'] ?? 5000)) {
            $errors['reason_text'][] = __('justification.text_too_long', ['max' => $req['max'] ?? 5000]);
        }
        $code = null;
        $codeRecord = null;
        $label = null;
        $codeUuid = $payload['reason_code'] ?? null;
        if ($codeUuid !== null) {
            if ($req['codes'] === 'none' || ! is_string($codeUuid)) {
                $errors['reason_code'][] = __('justification.code_not_accepted');
            } else {
                $code = ReasonCode::query()->where('uuid', strtolower($codeUuid))->where('is_active', true)->whereIn('set_key', array_keys($req['sets']))->first();
                if ($code !== null) {
                    $label = $code->translate('label') ?? $code->code;
                    if ($code->requires_note && trim((string) ($payload['note'] ?? '')) === '') {
                        $errors['note'][] = __('justification.note_required');
                    }
                } else {
                    [$codeRecord, $label] = $this->collectionCode(array_keys($req['collections']), strtolower($codeUuid));
                    if ($codeRecord === null) {
                        $errors['reason_code'][] = __('justification.unknown_code');
                    }
                }
            }
        } elseif ($req['codes'] === 'required') {
            $errors['reason_code'][] = __('justification.code_required');
        }
        if (mb_strlen((string) ($payload['note'] ?? '')) > 2000) {
            $errors['note'][] = __('justification.text_too_long', ['max' => 2000]);
        }
        $files = array_values(array_unique(array_filter((array) ($payload['attachments'] ?? []), 'is_string')));
        if ($files !== [] && $req['attachments'] === 'none') {
            $errors['attachments'][] = __('justification.attachments_not_accepted');
        }
        if ($files === [] && $req['attachments'] === 'required') {
            $errors['attachments'][] = __('justification.attachments_required');
        }
        if (count($files) > ($req['maxAttachments'] ?? 10)) {
            $errors['attachments'][] = __('justification.too_many_attachments', ['max' => $req['maxAttachments'] ?? 10]);
        }
        $stored = StoredFile::query()->whereIn('uuid', $files)->get();
        foreach ($stored as $f) {
            if (! $f->is_temporary || $f->uploaded_by !== $user->id) {
                $errors['attachments'][] = __('records.rules.file_missing');
            } elseif (! $this->fileAllowed($f, $req['attachmentRules'])) {
                $errors['attachments'][] = __('justification.file_not_allowed', ['name' => $f->original_name]);
            }
        }
        if ($stored->count() !== count($files)) {
            $errors['attachments'][] = __('records.rules.file_missing');
        }
        if ($errors !== []) {
            throw new RecordException(422, 'justification_invalid', __('justification.invalid'), ['errors' => array_map(static fn ($e) => array_values(array_unique($e)), $errors), 'justification' => $this->prompt($rt, $req, $change, $user)]);
        }

        return [
            'rules' => $req['rules'], 'reason_text' => $text === '' ? null : $text, 'reason_code_id' => $code?->id, 'reason_code_record_id' => $codeRecord,
            'label' => $label, 'note' => ($n = trim((string) ($payload['note'] ?? ''))) === '' ? null : $n, 'files' => $stored->all(),
        ];
    }

    /**
     * Saves a justification and its attachments (immutable, hashed) in the
     * caller's transaction. Returns its id.
     *
     * @param  array<string, mixed>  $validated  from enforce()
     * @param  list<array{field: string, old: mixed, new: mixed}>  $changedFields
     */
    public function record(FormRuntime $rt, ?int $recordId, User $user, string $context, array $validated, array $changedFields, int $affected = 1, ?int $onBehalfOf = null): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $content = [
            'form' => $rt->form->uuid, 'record' => $recordId, 'context' => $context, 'rules' => $validated['rules'],
            'reason_text' => $validated['reason_text'], 'reason_code_id' => $validated['reason_code_id'], 'reason_code_record_id' => $validated['reason_code_record_id'],
            'label' => $validated['label'], 'note' => $validated['note'], 'changed_fields' => $changedFields, 'affected' => $affected,
            'user' => $user->id, 'on_behalf_of' => $onBehalfOf, 'at' => $now,
            'attachments' => array_map(static fn (StoredFile $f) => [$f->uuid, $f->sha256], $validated['files']),
        ];
        $j = Justification::query()->create([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $rt->form->organization_id, 'form_id' => $rt->form->id, 'record_id' => $recordId,
            'context' => $context, 'rule_ids' => $validated['rules'], 'reason_text' => $validated['reason_text'],
            'reason_code_id' => $validated['reason_code_id'], 'reason_code_record_id' => $validated['reason_code_record_id'],
            'reason_code_label_snapshot' => $validated['label'] === null ? null : mb_substr((string) $validated['label'], 0, 255),
            'note' => $validated['note'], 'changed_fields' => $changedFields, 'affected_count' => $affected,
            'user_id' => $user->id, 'on_behalf_of_user_id' => $onBehalfOf, 'locale' => app()->getLocale(),
            'content_hash' => hash('sha256', (string) json_encode(Canonical::of($content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'created_at' => $now,
        ]);
        foreach ($validated['files'] as $file) {
            /** @var StoredFile $file */
            $file->forceFill(['is_temporary' => false, 'form_id' => $rt->form->id, 'record_id' => $recordId, 'field_id' => null, 'owner_type' => 'justification', 'owner_id' => $j->id])->save();
            DB::table('justification_attachments')->insert(['justification_id' => $j->id, 'file_id' => $file->id, 'created_at' => $now]);
        }

        return $j->id;
    }

    /**
     * The prompt shown at save time: title and help in the user's language,
     * constraints, reason codes, attachments, and (when asked) the fields
     * that change.
     *
     * @param  array<string, mixed>  $req
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    public function prompt(FormRuntime $rt, array $req, array $change, User $user): array
    {
        $codes = [];
        foreach (ReasonCode::query()->whereIn('set_key', array_keys($req['sets']))->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $c) {
            $codes[] = ['uuid' => strtolower($c->uuid), 'code' => $c->code, 'label' => $c->translate('label') ?? $c->code, 'requires_note' => $c->requires_note];
        }
        $collections = [];
        foreach (array_keys($req['collections']) as $uuid) {
            $collections[] = $uuid;
        }
        $summary = [];
        if ($req['summary']) {
            foreach ($change['fields'] ?? [] as $key) {
                $uuid = $rt->keys[$key] ?? ($rt->repeaterKeys[$key] ?? null);
                $f = $uuid === null ? null : ($rt->fields[$uuid] ?? null);
                $label = $f === null ? ($rt->groups[$uuid ?? '']['i18n']['title'][app()->getLocale()] ?? $key) : ($f['i18n']['label'][app()->getLocale()] ?? $f['i18n']['label'][$this->translator->defaultLocale()] ?? $key);
                $mask = $f !== null && (($f['flags']['sensitive'] ?? false) || ($f['flags']['encrypted'] ?? false));
                $summary[] = ['field' => $key, 'label' => $label, 'old' => $mask ? '«masked»' : ($change['old'][$key] ?? null), 'new' => $mask ? '«masked»' : ($change['values'][$key] ?? null)];
            }
        }
        $locale = app()->getLocale();
        $default = $this->translator->defaultLocale();
        $pick = static fn ($m) => $m === null ? null : ((array) $m)[$locale] ?? ((array) $m)[$default] ?? null;

        return [
            'level' => $req['level'],
            'title' => $pick($req['title']),
            'help' => $pick($req['help']),
            'text' => ['min' => $req['min'], 'max' => $req['max'] ?? 5000, 'required' => $req['level'] === 'mandatory' && $req['codes'] !== 'required'],
            'reason_codes' => ['mode' => $req['codes'], 'options' => $codes, 'collections' => $collections],
            'attachments' => ['mode' => $req['attachments'], 'max' => $req['maxAttachments'] ?? 10, 'rules' => $req['attachmentRules']],
            'changes' => $summary,
        ];
    }

    /**
     * Rules that apply to the change and the actor: explicit rules plus the
     * builder's field and group settings; per (scope, target) only the most
     * specific subject tier present counts.
     *
     * @param  array<string, mixed>  $change
     * @return list<array<string, mixed>>
     */
    private function matching(FormRuntime $rt, User $user, string $context, array $change): array
    {
        $changedFields = [];
        $changedGroups = [];
        foreach ($change['fields'] ?? [] as $key) {
            $uuid = $rt->keys[$key] ?? null;
            if ($uuid !== null) {
                $changedFields[$uuid] = true;
                $g = $rt->fields[$uuid]['group'] ?? null;
            } else {
                $g = $rt->repeaterKeys[$key] ?? null;
                foreach ($rt->repeaters[$g]['fields'] ?? [] as $rowField) {
                    $changedFields[$rowField] = true;
                }
            }
            for ($i = 0; $g !== null && $i < 20; $i++) {
                $changedGroups[$g] = true;
                $g = $rt->groups[$g]['parent'] ?? null;
            }
        }
        $editLike = $changedFields !== [] && in_array($context, ['edit', 'transition'], true);
        $wanted = static function (array $r) use ($context, $change, $editLike, $changedFields, $changedGroups): bool {
            return match ($r['scope']) {
                'form' => $editLike,
                'group' => $editLike && isset($changedGroups[$r['target']]),
                'field' => $editLike && isset($changedFields[$r['target']]),
                'status' => $editLike && $r['statusId'] !== null && $r['statusId'] === ($change['status'] ?? null),
                'transition' => $context === 'transition' && $r['transitionId'] !== null && $r['transitionId'] === ($change['transition'] ?? null),
                'delete', 'restore', 'reassign' => $context === $r['scope'],
                default => false,
            };
        };

        $candidates = [];
        foreach ($this->explicit($rt) as $r) {
            if ($r['active'] && $wanted($r) && $this->subjectMatches($user, $r['subject_type'], $r['subject_id'])) {
                $candidates[] = $r;
            }
        }
        if ($editLike) {
            foreach ($rt->fields as $uuid => $f) {
                if (isset($changedFields[$uuid]) && in_array($f['justification'] ?? 'inherit', self::LEVELS_SET, true)) {
                    $candidates[] = $this->implicit('field', $uuid, $f['justification']);
                }
            }
            foreach ($rt->groups as $uuid => $g) {
                if (isset($changedGroups[$uuid]) && in_array($g['justification'] ?? 'inherit', self::LEVELS_SET, true)) {
                    $candidates[] = $this->implicit('group', $uuid, $g['justification']);
                }
            }
        }
        $best = [];
        foreach ($candidates as $r) {
            $k = $r['scope'].':'.($r['target'] ?? '');
            $best[$k] = max($best[$k] ?? 0, self::TIER[$r['subject_type']]);
        }

        return array_values(array_filter($candidates, static fn (array $r) => self::TIER[$r['subject_type']] === $best[$r['scope'].':'.($r['target'] ?? '')]));
    }

    private const LEVELS_SET = ['not_required', 'optional', 'mandatory'];

    /** @return array<string, mixed> */
    private function implicit(string $scope, string $target, string $level): array
    {
        return [
            'uuid' => $scope.':'.$target, 'scope' => $scope, 'target' => $target, 'subject_type' => 'everyone', 'subject_id' => null,
            'level' => $level, 'condition' => null, 'levelWhen' => null, 'text' => [], 'reasonCodes' => ['mode' => 'none'],
            'attachments' => ['mode' => 'none'], 'showSummary' => false, 'active' => true, 'title' => null, 'help' => null,
        ];
    }

    /** @var array<int, list<array<string, mixed>>> */
    private array $memo = [];

    /** @return list<array<string, mixed>> */
    private function explicit(FormRuntime $rt): array
    {
        if (isset($this->memo[$rt->form->id])) {
            return $this->memo[$rt->form->id];
        }
        $rows = DB::table('justification_rules')->where('form_id', $rt->form->id)->where('is_active', true)->orderBy('id')->get();
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());
        $uuid = [
            'group' => DB::table('field_groups')->whereIn('id', $rows->pluck('group_id')->filter()->all())->pluck('uuid', 'id')->all(),
            'field' => DB::table('fields')->whereIn('id', $rows->pluck('field_id')->filter()->all())->pluck('uuid', 'id')->all(),
            'collection' => DB::table('forms')->whereIn('id', $rows->pluck('reason_code_collection_id')->filter()->all())->pluck('uuid', 'id')->all(),
        ];
        $tr = $this->translator->allMany('justification_rule', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());

        return $this->memo[$rt->form->id] = $rows->map(static fn ($r) => [
            'uuid' => strtolower((string) $r->uuid), 'scope' => $r->scope,
            'target' => match ($r->scope) {
                'group' => strtolower((string) ($uuid['group'][$r->group_id] ?? '')),
                'field' => strtolower((string) ($uuid['field'][$r->field_id] ?? '')),
                default => null,
            },
            'statusId' => $r->status_id === null ? null : (int) $r->status_id,
            'transitionId' => $r->transition_id === null ? null : (int) $r->transition_id,
            'subject_type' => $r->subject_type, 'subject_id' => $r->subject_id === null ? null : (int) $r->subject_id,
            'level' => $r->level, 'condition' => $r->condition_id === null ? null : ($asts[(int) $r->condition_id] ?? null), 'levelWhen' => $r->level_when_condition,
            'text' => ['min' => $r->min_length, 'max' => $r->max_length],
            'reasonCodes' => ['mode' => $r->reason_code_mode, 'source' => $r->reason_code_source, 'set' => $r->reason_code_set, 'collection' => $r->reason_code_collection_id === null ? null : strtolower((string) ($uuid['collection'][$r->reason_code_collection_id] ?? ''))],
            'attachments' => ['mode' => $r->attachments_mode, 'max' => $r->max_attachments, 'rules' => json_decode((string) ($r->attachment_rules ?? 'null'), true)],
            'showSummary' => (bool) $r->show_change_summary, 'active' => (bool) $r->is_active,
            'title' => $tr[(int) $r->id]['title'] ?? null, 'help' => $tr[(int) $r->id]['help'] ?? null,
        ])->all();
    }

    private function subjectMatches(User $user, string $type, ?int $id): bool
    {
        return match ($type) {
            'everyone' => true,
            'user' => $id === $user->id,
            'role' => $id !== null && $user->activeRoles()->where('roles.id', $id)->exists(),
            'department' => $id !== null && $user->department_id !== null && DB::table('department_closure')->where('descendant_id', $user->department_id)->where('ancestor_id', $id)->exists(),
            default => false,
        };
    }

    /**
     * A record of a reason-code collection: its id and title.
     *
     * @param  list<string>  $collections  collection form uuids
     * @return array{0: int|null, 1: string|null}
     */
    private function collectionCode(array $collections, string $recordUuid): array
    {
        foreach ($collections as $formUuid) {
            $rt = $this->runtimes->forUuid($formUuid);
            $row = $rt === null ? null : DB::table($rt->table)->where('uuid', $recordUuid)->whereNull('deleted_at')->first();
            if ($row !== null) {
                return [(int) $row->id, $this->references->recordTitle($rt->definition, $row)];
            }
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function mergeFileRules(array $a, array $b): array
    {
        if ($a === []) {
            return $b;
        }
        if (isset($b['types'])) {
            $a['types'] = isset($a['types']) ? array_values(array_intersect((array) $a['types'], (array) $b['types'])) : $b['types'];
        }
        if (isset($b['maxSizeKb'])) {
            $a['maxSizeKb'] = min((int) ($a['maxSizeKb'] ?? PHP_INT_MAX), (int) $b['maxSizeKb']);
        }

        return $a;
    }

    /** @param  array<string, mixed>  $rules */
    private function fileAllowed(StoredFile $f, array $rules): bool
    {
        if (isset($rules['maxSizeKb']) && $f->size_bytes > (int) $rules['maxSizeKb'] * 1024) {
            return false;
        }
        if (! empty($rules['types'])) {
            $ext = strtolower(pathinfo((string) $f->original_name, PATHINFO_EXTENSION));
            $types = array_map(static fn ($t) => strtolower(ltrim((string) $t, '.')), (array) $rules['types']);

            return in_array($ext, $types, true) || in_array(strtolower((string) $f->mime_type), $types, true);
        }

        return true;
    }
}
