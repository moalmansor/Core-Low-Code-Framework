<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A sync job (specification §4.33, ADR-0057): a scheduled or on-demand
 * import from an external data source into a form, or export of a form's
 * records to a REST address, with a field mapping, matching keys and a
 * conflict rule. Its name is translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $external_data_source_id
 * @property int $form_id
 * @property string $direction
 * @property array<string, mixed> $field_mapping
 * @property array<string, mixed> $match_keys
 * @property string $conflict_rule
 * @property int $run_as_user_id
 * @property int|null $scheduled_task_id
 * @property bool $is_active
 */
final class SyncJob extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'sync_jobs';

    protected function casts(): array
    {
        return ['field_mapping' => 'array', 'match_keys' => 'array', 'is_active' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'sync_job';
    }
}
