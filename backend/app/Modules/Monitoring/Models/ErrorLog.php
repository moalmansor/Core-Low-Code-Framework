<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use App\Support\Models\BaseModel;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $occurred_at
 * @property int|null $organization_id
 * @property int $error_group_id
 * @property string $reference_code
 * @property string $severity
 * @property string $exception_class
 * @property string $message
 * @property array<string, mixed>|null $request
 * @property int|null $user_id
 * @property string|null $correlation_id
 */
final class ErrorLog extends BaseModel
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'request' => 'array', 'role_keys' => 'array'];
    }
}
