<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A reusable field or group subtree in the field library (specification §4.3).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $application_id
 * @property string $kind
 * @property string|null $category
 * @property array<string, mixed> $definition
 * @property int $usage_count
 */
final class FieldTemplate extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = ['application_id', 'kind', 'category', 'definition', 'usage_count'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'usage_count' => 'integer'];
    }

    public function translationType(): string
    {
        return 'field_template';
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'field_template';
    }
}
