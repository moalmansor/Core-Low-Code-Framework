<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A dashboard (specification §4.18): widgets laid out on a grid, each shown
 * to the roles chosen for it. Its name and description are translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $application_id
 * @property string $key
 * @property array<string, mixed> $layout
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class Dashboard extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $table = 'dashboards';

    protected function casts(): array
    {
        return ['layout' => 'array', 'is_active' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'dashboard';
    }
}
