<?php

declare(strict_types=1);

namespace App\Modules\Justification\Models;

use App\Support\Models\BaseModel;
use LogicException;

/**
 * A saved justification (specification §4.24): immutable once written. No
 * interface edits or deletes one, Super Admin included; a correction is a new
 * entry. The model refuses updates and deletes, and `content_hash` lets an
 * auditor verify the stored content.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $form_id
 * @property int|null $record_id
 * @property string $context
 * @property list<string> $rule_ids
 * @property string|null $reason_text
 * @property int|null $reason_code_id
 * @property int|null $reason_code_record_id
 * @property string|null $reason_code_label_snapshot
 * @property string|null $note
 * @property list<array<string, mixed>> $changed_fields
 * @property int $affected_count
 * @property int|null $user_id
 * @property int|null $on_behalf_of_user_id
 * @property string $locale
 * @property string $content_hash
 */
final class Justification extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'justifications';

    protected $fillable = [
        'uuid', 'organization_id', 'form_id', 'record_id', 'context', 'rule_ids', 'reason_text', 'reason_code_id', 'reason_code_record_id',
        'reason_code_label_snapshot', 'note', 'changed_fields', 'affected_count', 'user_id', 'on_behalf_of_user_id', 'locale', 'content_hash', 'created_at',
    ];

    protected function casts(): array
    {
        return ['rule_ids' => 'array', 'changed_fields' => 'array', 'affected_count' => 'integer'];
    }

    protected static function booted(): void
    {
        self::updating(static fn () => throw new LogicException('Justifications are immutable.'));
        self::deleting(static fn () => throw new LogicException('Justifications are immutable.'));
    }
}
