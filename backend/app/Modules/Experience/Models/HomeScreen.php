<?php

declare(strict_types=1);

namespace App\Modules\Experience\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A home screen (specification §4.29): the landing page for everyone, a
 * role, a department or one person, either the main one or an
 * application's, assembled from widgets. Its name is translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $application_id
 * @property string $target_type
 * @property int|null $target_id
 * @property int $priority
 * @property array<string, mixed> $layout
 * @property bool $is_active
 */
final class HomeScreen extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'home_screens';

    protected function casts(): array
    {
        return ['layout' => 'array', 'is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function translationType(): string
    {
        return 'home_screen';
    }
}
