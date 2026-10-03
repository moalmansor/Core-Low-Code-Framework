<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Models\BaseModel;

/**
 * @property int $id
 * @property string $identifier_hash
 * @property int|null $user_id
 * @property bool $successful
 * @property string|null $failure_reason
 */
final class LoginAttempt extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'identifier_hash', 'user_id', 'guard', 'ip_address', 'user_agent', 'successful', 'failure_reason', 'attempted_at'];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'attempted_at' => 'datetime'];
    }
}
