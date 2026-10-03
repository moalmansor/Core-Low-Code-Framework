<?php

declare(strict_types=1);

namespace App\Modules\Schema\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use Illuminate\Support\Carbon;

/**
 * A publish lock on one form (architecture §12.4). `held_key` is the form id
 * while held and unique, so at most one holder exists per form.
 *
 * @property int $id
 * @property int $form_id
 * @property string $lock_group
 * @property string $status
 * @property int|null $migration_plan_id
 * @property int $owner_user_id
 * @property int|null $blocked_by_lock_id
 * @property Carbon|null $acquired_at
 * @property Carbon|null $heartbeat_at
 * @property Carbon $expires_at
 * @property string|null $held_key
 */
final class PublishLock extends BaseModel
{
    use BelongsToOrganization;

    protected $fillable = ['form_id', 'lock_group', 'status', 'migration_plan_id', 'owner_user_id', 'blocked_by_lock_id', 'acquired_at', 'heartbeat_at', 'expires_at', 'held_key'];

    protected function casts(): array
    {
        return ['acquired_at' => 'datetime', 'heartbeat_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
