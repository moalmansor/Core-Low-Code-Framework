<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An incoming endpoint of one form (specification §4.33, ADR-0050): other
 * systems post JSON to `/api/inbound/{slug}` to create or update records.
 * `api` endpoints take a personal API token with write access to the form;
 * `webhook` endpoints take a signature made with a secret kept encrypted.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $form_id
 * @property string $kind
 * @property string $slug
 * @property string $operation
 * @property int|null $match_field_id
 * @property array<string, string> $payload_mapping
 * @property string|null $signature_secret
 * @property string|null $signature_header
 * @property string|null $signature_algorithm
 * @property int $run_as_user_id
 * @property int|null $initial_status_id
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class InboundEndpoint extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $table = 'inbound_endpoints';

    protected $hidden = ['signature_secret'];

    protected function casts(): array
    {
        return ['payload_mapping' => 'array', 'signature_secret' => 'encrypted', 'is_active' => 'boolean'];
    }
}
