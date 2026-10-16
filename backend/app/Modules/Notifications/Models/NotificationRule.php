<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A form's notification rule (specification §4.16): when it fires, whom it
 * tells on which channels, with which template.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property int $form_id
 */
final class NotificationRule extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'notification_rules';

    protected $fillable = ['form_id', 'key'];

    public function translationType(): string
    {
        return 'notification_rule';
    }
}
