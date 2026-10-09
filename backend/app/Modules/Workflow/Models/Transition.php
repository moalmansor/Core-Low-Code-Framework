<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A move between statuses (specification §4.12, §4.25): who may perform it is
 * the permission `transition.{uuid}.perform`; it may need a condition,
 * required fields, a comment, attachments and approvals.
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property string $key
 * @property int|null $from_status_id
 * @property int $to_status_id
 * @property int|null $condition_id
 * @property list<string>|null $required_fields
 * @property string $comment_level
 * @property string $attachments_level
 * @property string $approval_mode
 * @property array<string, mixed>|null $approval_config
 * @property string $rejection_behavior
 * @property int|null $rejection_status_id
 * @property bool $confirmation
 * @property array<string, mixed>|null $button_style
 * @property int $sort_order
 * @property array<string, mixed>|null $diagram_edge
 */
final class Transition extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = [
        'form_id', 'key', 'from_status_id', 'to_status_id', 'condition_id', 'required_fields', 'comment_level', 'attachments_level',
        'approval_mode', 'approval_config', 'rejection_behavior', 'rejection_status_id', 'confirmation', 'button_style', 'sort_order', 'diagram_edge', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'required_fields' => 'array', 'approval_config' => 'array', 'button_style' => 'array', 'diagram_edge' => 'array',
            'confirmation' => 'boolean', 'sort_order' => 'integer', 'archived_at' => 'datetime',
        ];
    }

    public function translationType(): string
    {
        return 'transition';
    }
}
