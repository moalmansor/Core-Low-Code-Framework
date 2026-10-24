<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An external data source (specification §4.33, ADR-0057): a REST endpoint
 * called through the egress gateway, or a view in a database connection
 * defined in the environment. Credentials and headers are kept encrypted.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $key
 * @property string $type
 * @property array<string, mixed>|null $rest_config
 * @property string|null $db_connection
 * @property string|null $db_view
 * @property string $auth_type
 * @property array<string, string>|null $credentials
 * @property list<array{name: string, value: string}>|null $headers
 * @property array<string, mixed> $response_mapping
 * @property string $cache_policy
 * @property int|null $cache_ttl_seconds
 * @property bool $is_active
 * @property string|null $last_tested_at
 * @property string|null $last_test_status
 * @property string $updated_at
 */
final class ExternalDataSource extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'external_data_sources';

    protected $hidden = ['credentials', 'headers'];

    protected function casts(): array
    {
        return ['rest_config' => 'array', 'credentials' => 'encrypted:array', 'headers' => 'encrypted:array', 'response_mapping' => 'array',
            'is_active' => 'boolean', 'cache_ttl_seconds' => 'integer'];
    }

    public function translationType(): string
    {
        return 'external_data_source';
    }
}
