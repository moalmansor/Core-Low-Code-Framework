<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * @property int $id
 * @property string $uuid
 * @property string $host_pattern
 * @property list<int> $ports
 * @property bool $allow_http
 * @property string|null $description
 * @property bool $is_active
 */
final class EgressAllowlistEntry extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $table = 'egress_allowlist';

    protected $fillable = ['host_pattern', 'ports', 'allow_http', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['ports' => 'array', 'allow_http' => 'boolean', 'is_active' => 'boolean'];
    }
}
