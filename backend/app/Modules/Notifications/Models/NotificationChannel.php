<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A notification channel beyond email and in-app (specification §4.33,
 * ADR-0056): an SMS or messaging provider, chosen per notification rule.
 * Its provider settings are kept encrypted; its name is translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $key
 * @property string $type
 * @property string $provider
 * @property array<string, mixed>|null $config
 * @property int|null $rate_limit_per_minute
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class NotificationChannel extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'notification_channels';

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'is_active' => 'boolean', 'is_default' => 'boolean', 'rate_limit_per_minute' => 'integer'];
    }

    public function translationType(): string
    {
        return 'notification_channel';
    }
}
