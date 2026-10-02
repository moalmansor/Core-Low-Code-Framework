<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property bool $is_platform
 * @property string $status
 * @property string $default_locale
 * @property string $timezone
 */
final class Organization extends BaseModel
{
    use HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['parent_id', 'key', 'is_platform', 'status', 'default_locale', 'timezone', 'settings_overrides'];

    protected function casts(): array
    {
        return ['is_platform' => 'boolean', 'settings_overrides' => 'array'];
    }

    public function translationType(): string
    {
        return 'organization';
    }
}
