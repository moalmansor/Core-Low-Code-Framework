<?php

declare(strict_types=1);

namespace App\Modules\Experience\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An announcement or banner (specification §4.29): system-wide or for one
 * application, scheduled, optionally dismissible, for some roles, with a
 * severity. Its title and body are translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $application_id
 * @property string $severity
 * @property string $placement
 * @property list<int>|null $audience_role_ids
 * @property string $starts_at
 * @property string|null $ends_at
 * @property bool $is_dismissible
 * @property bool $is_published
 * @property int|null $published_by
 */
final class Announcement extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $table = 'announcements';

    protected function casts(): array
    {
        return ['audience_role_ids' => 'array', 'is_dismissible' => 'boolean', 'is_published' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'announcement';
    }
}
