<?php

declare(strict_types=1);

namespace App\Modules\Justification\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * An admin-defined reason code (specification §4.24), grouped in sets that
 * justification rules refer to; "Other"-like codes require a note.
 *
 * @property int $id
 * @property string $uuid
 * @property string $set_key
 * @property string $code
 * @property bool $requires_note
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $updated_at
 */
final class ReasonCode extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['label'];

    protected $table = 'justification_reason_codes';

    protected $fillable = ['set_key', 'code', 'requires_note', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['requires_note' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function translationType(): string
    {
        return 'justification_reason_code';
    }
}
