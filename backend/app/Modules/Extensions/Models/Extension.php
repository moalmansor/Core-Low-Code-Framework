<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A developer extension (specification §4.19): a server hook, a custom API
 * endpoint, or a client validator, field component or form script. Its
 * code lives in versions; one version may be deployed.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $key
 * @property string $type
 * @property string|null $hook_point
 * @property int|null $form_id
 * @property string $status
 * @property int $timeout_ms
 * @property int|null $current_version_id
 * @property int|null $deployed_version_id
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class Extension extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    public const SERVER = ['server_hook', 'api_endpoint'];

    public const CLIENT = ['client_validator', 'client_field', 'form_script'];

    protected $table = 'extensions';

    protected function casts(): array
    {
        return ['timeout_ms' => 'integer'];
    }

    public function isServer(): bool
    {
        return in_array($this->type, self::SERVER, true);
    }
}
