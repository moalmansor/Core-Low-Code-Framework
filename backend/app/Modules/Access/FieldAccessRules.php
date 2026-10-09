<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Models\Form;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes the sparse form/group/field access overrides (architecture §16.4):
 * one row per coordinate (target, subject, status, mode), identified by a
 * nullable-safe `rule_hash`. "Reset to inherited" deletes the row. Every
 * change bumps the access epoch and is audited.
 */
final class FieldAccessRules
{
    public function __construct(private readonly AccessCache $cache, private readonly AuditWriter $audit) {}

    public static function hash(int $formId, string $targetType, ?int $groupId, ?int $fieldId, string $subjectType, ?int $subjectId, ?int $statusId, ?string $mode): string
    {
        return hash('sha256', implode('|', [$formId, $targetType, $groupId ?? '-', $fieldId ?? '-', $subjectType, $subjectId ?? '-', $statusId ?? '-', $mode ?? '-']));
    }

    /** Upserts a rule; `access === null` resets the coordinate to inherited. Returns the previous access/effect. */
    public function put(Form $form, string $targetType, ?int $groupId, ?int $fieldId, string $subjectType, ?int $subjectId, ?string $mode, ?string $access, ?string $effect, ?int $statusId = null): ?array
    {
        $hash = self::hash($form->id, $targetType, $groupId, $fieldId, $subjectType, $subjectId, $statusId, $mode);
        $existing = DB::table('field_access_rules')->where('rule_hash', $hash)->first();
        $before = $existing === null ? null : ['access' => $existing->access, 'effect' => $existing->effect];
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        if ($access === null) {
            if ($existing !== null) {
                DB::table('field_access_rules')->where('id', $existing->id)->delete();
            }
        } elseif ($existing !== null) {
            DB::table('field_access_rules')->where('id', $existing->id)->update(['access' => $access, 'effect' => $effect ?? 'allow', 'updated_at' => $now, 'updated_by' => Auth::id()]);
        } else {
            DB::table('field_access_rules')->insert([
                'uuid' => (string) Str::uuid7(), 'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now,
                'created_by' => Auth::id(), 'updated_by' => Auth::id(), 'form_id' => $form->id, 'target_type' => $targetType,
                'group_id' => $groupId, 'field_id' => $fieldId, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
                'mode' => $mode, 'status_id' => $statusId, 'access' => $access, 'effect' => $effect ?? 'allow', 'rule_hash' => $hash,
            ]);
        }
        if ($before !== ($access === null ? null : ['access' => $access, 'effect' => $effect ?? 'allow'])) {
            $this->audit->record('access.field_rule_changed', 'access', [['field_key' => $targetType.':'.($fieldId ?? $groupId ?? $form->id).':'.$subjectType.':'.($subjectId ?? '*').':'.($mode ?? '*').':'.($statusId ?? '*'), 'old' => $before, 'new' => $access === null ? null : ['access' => $access, 'effect' => $effect ?? 'allow']]], 'form', $form->id);
            $this->cache->bump();
        }

        return $before;
    }
}
