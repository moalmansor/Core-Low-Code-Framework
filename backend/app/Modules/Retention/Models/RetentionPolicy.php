<?php

declare(strict_types=1);

namespace App\Modules\Retention\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A retention policy (specification §4.26): for one data class — and, for
 * records, optionally one application or form — how many days data is kept
 * and what happens to it then (archive to cold storage, export then delete,
 * or delete). Runs on its own schedule; its name is translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $data_class
 * @property int|null $application_id
 * @property int|null $form_id
 * @property int $retention_days
 * @property string $date_basis
 * @property string $end_action
 * @property string|null $archive_disk
 * @property int|null $scheduled_task_id
 * @property bool $is_active
 */
final class RetentionPolicy extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'retention_policies';

    protected function casts(): array
    {
        return ['retention_days' => 'integer', 'is_active' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'retention_policy';
    }
}
