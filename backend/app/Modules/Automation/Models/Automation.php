<?php

declare(strict_types=1);

namespace App\Modules\Automation\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An automation (specification §4.31): its name and description are
 * translatable; the rest is read and written by AutomationDocument.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property int|null $form_id
 * @property bool $is_enabled
 */
final class Automation extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $table = 'automations';

    protected $fillable = ['key', 'form_id', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'automation';
    }
}
