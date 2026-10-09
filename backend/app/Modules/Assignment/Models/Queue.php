<?php

declare(strict_types=1);

namespace App\Modules\Assignment\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A role or department work queue (specification §4.25): which forms appear
 * in it with which columns, and how long a claim lasts.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property string $type
 * @property int|null $role_id
 * @property int|null $department_id
 * @property int|null $claim_timeout_minutes
 * @property bool $is_active
 * @property Carbon|null $updated_at
 */
final class Queue extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['key', 'type', 'role_id', 'department_id', 'claim_timeout_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'claim_timeout_minutes' => 'integer'];
    }

    public function translationType(): string
    {
        return 'queue';
    }
}
