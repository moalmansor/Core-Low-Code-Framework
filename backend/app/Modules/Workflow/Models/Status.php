<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A workflow status of a form (specification §4.12). Records point at it by
 * id, so a status removed from the workflow is archived, never deleted.
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property string $key
 * @property string $color
 * @property string|null $icon
 * @property bool $is_initial
 * @property bool $is_final
 * @property int $sort_order
 * @property array<string, mixed>|null $diagram_position
 * @property Carbon|null $archived_at
 */
final class Status extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['form_id', 'key', 'color', 'icon', 'is_initial', 'is_final', 'sort_order', 'diagram_position', 'archived_at'];

    protected function casts(): array
    {
        return ['is_initial' => 'boolean', 'is_final' => 'boolean', 'sort_order' => 'integer', 'diagram_position' => 'array', 'archived_at' => 'datetime'];
    }

    public function translationType(): string
    {
        return 'status';
    }
}
