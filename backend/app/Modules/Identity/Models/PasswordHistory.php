<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Models\BaseModel;

/**
 * @property int $id
 * @property int $user_id
 * @property string $password_hash
 */
final class PasswordHistory extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'password_hash'];
}
