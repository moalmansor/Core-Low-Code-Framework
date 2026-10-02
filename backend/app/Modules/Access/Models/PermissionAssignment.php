<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permission_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $effect
 * @property bool $include_descendants
 * @property Carbon|null $valid_until
 * @property int|null $granted_by
 */
final class PermissionAssignment extends BaseModel
{
    use BelongsToOrganization;

    protected $fillable = ['permission_id', 'subject_type', 'subject_id', 'effect', 'include_descendants', 'valid_until', 'granted_by'];

    protected function casts(): array
    {
        return ['include_descendants' => 'boolean', 'valid_until' => 'datetime'];
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
