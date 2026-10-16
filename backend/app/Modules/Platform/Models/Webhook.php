<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An outgoing webhook (specification §4.22): record events of one form, or
 * of every form, posted to an allowlisted address and signed with a secret
 * kept encrypted. Its name is translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $form_id
 * @property string $key
 * @property string $url
 * @property string $method
 * @property list<array{name: string, value: string}>|null $headers
 * @property list<string> $events
 * @property string $secret
 * @property int $timeout_seconds
 * @property int $max_retries
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class Webhook extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'webhooks';

    protected $hidden = ['secret', 'headers'];

    protected function casts(): array
    {
        return ['headers' => 'encrypted:array', 'events' => 'array', 'secret' => 'encrypted', 'is_active' => 'boolean', 'timeout_seconds' => 'integer', 'max_retries' => 'integer'];
    }

    public function translationType(): string
    {
        return 'webhook';
    }
}
