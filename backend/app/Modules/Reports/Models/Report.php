<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A report (specification §4.18): a base form, the paths it reads through
 * relations, grouping, measures, filters and how it is shown. Its name and
 * description are translatable; labels inside the definition are kept per
 * language in the definition.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $application_id
 * @property int $base_form_id
 * @property string $key
 * @property array<string, mixed> $definition
 * @property string $visualization
 * @property bool $is_personal
 * @property int|null $owner_user_id
 * @property int|null $cache_ttl_seconds
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class Report extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $table = 'reports';

    protected function casts(): array
    {
        return ['definition' => 'array', 'is_personal' => 'boolean', 'cache_ttl_seconds' => 'integer'];
    }

    public function translationType(): string
    {
        return 'report';
    }
}
