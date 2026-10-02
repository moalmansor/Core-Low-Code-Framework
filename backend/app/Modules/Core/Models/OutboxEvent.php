<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Support\Models\BaseModel;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $correlation_id
 * @property Carbon $available_at
 * @property Carbon|null $dispatched_at
 * @property int $attempts
 * @property string|null $last_error
 */
final class OutboxEvent extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'event_type', 'payload', 'correlation_id', 'available_at', 'dispatched_at', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'dispatched_at' => 'datetime', 'attempts' => 'integer'];
    }
}
