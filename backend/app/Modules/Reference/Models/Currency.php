<?php

declare(strict_types=1);

namespace App\Modules\Reference\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * Reference data (specification §4.34, architecture §10.15): `currencies`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $code
 * @property string|null $symbol
 * @property int $decimals
 * @property string $rounding
 * @property string $symbol_position
 * @property bool $is_base
 * @property bool $is_enabled
 */
final class Currency extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    public function translationType(): string
    {
        return 'currency';
    }

    protected $table = 'currencies';

    protected $fillable = ['code', 'symbol', 'decimals', 'rounding', 'symbol_position', 'is_base', 'is_enabled'];

    protected function casts(): array
    {
        return ['decimals' => 'integer', 'is_base' => 'boolean', 'is_enabled' => 'boolean'];
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'currency';
    }
}
