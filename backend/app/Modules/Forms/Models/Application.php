<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An application (workspace) grouping forms, collections and menus
 * (specification §4.27).
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property string|null $icon
 * @property string|null $color
 * @property string $status
 * @property string $data_sharing_default
 * @property bool $maintenance_mode
 * @property Carbon|null $maintenance_until
 * @property array<string, mixed>|null $settings
 * @property int $sort_order
 */
final class Application extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, SoftDeletes, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'maintenance_message'];

    protected $fillable = ['key', 'icon', 'color', 'status', 'data_sharing_default', 'maintenance_mode', 'maintenance_until', 'settings', 'sort_order'];

    protected function casts(): array
    {
        return ['maintenance_mode' => 'boolean', 'maintenance_until' => 'datetime', 'settings' => 'array', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Form, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /** @return HasMany<MenuItem, $this> */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function translationType(): string
    {
        return 'application';
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'application';
    }
}
