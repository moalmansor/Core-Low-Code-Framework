<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;

/**
 * @property int $id
 * @property string $key
 * @property string $scope_type
 * @property int|null $scope_id
 * @property string $ability
 * @property string $category
 * @property bool $is_system
 * @property bool $is_dangerous
 */
final class Permission extends BaseModel
{
    use BelongsToOrganization, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['label', 'description'];

    protected $fillable = ['key', 'scope_type', 'scope_id', 'ability', 'category', 'is_system', 'is_dangerous'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_dangerous' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'permission';
    }
}
