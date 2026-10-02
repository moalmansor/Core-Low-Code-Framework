<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Support\Models\BaseModel;
use LogicException;

/**
 * Append-only audit entry (specification §4.20, ADR-0011). No code path can
 * update or delete one; corrections are new entries.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property int $organization_id
 * @property int $chain_id
 * @property int $chain_seq
 * @property string $event
 * @property string $category
 * @property string|null $object_type
 * @property int|null $object_id
 * @property list<array<string, mixed>>|null $changes
 * @property int|null $actor_user_id
 * @property int|null $subject_user_id
 * @property int|null $on_behalf_of_user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $meta
 * @property string $prev_hash
 * @property string $hash
 */
final class AuditLog extends BaseModel
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'changes' => 'array', 'meta' => 'array', 'chain_id' => 'integer', 'chain_seq' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Audit entries are immutable.'));
        static::deleting(static fn () => throw new LogicException('Audit entries cannot be deleted.'));
    }
}
