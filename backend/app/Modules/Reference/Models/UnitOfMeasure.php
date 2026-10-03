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
 * Reference data (specification §4.34, architecture §10.15): `units_of_measure`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $code
 * @property string $dimension
 * @property string|null $symbol
 * @property int|null $base_unit_id
 * @property string $factor
 * @property string $offset
 * @property int $precision
 */
final class UnitOfMeasure extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    public function translationType(): string
    {
        return 'unit_of_measure';
    }

    protected $table = 'units_of_measure';

    protected $fillable = ['code', 'dimension', 'symbol', 'base_unit_id', 'factor', 'offset', 'precision'];

    protected function casts(): array
    {
        return ['precision' => 'integer'];
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'unit_of_measure';
    }
}
