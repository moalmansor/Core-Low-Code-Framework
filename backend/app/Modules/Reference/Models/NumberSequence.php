<?php

declare(strict_types=1);

namespace App\Modules\Reference\Models;

use App\Modules\Audit\Auditable;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * Reference data (specification §4.34, architecture §10.15): `number_sequences`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $key
 * @property string $scope
 * @property int|null $form_id
 * @property int|null $field_id
 * @property string $pattern
 * @property string|null $prefix
 * @property int $padding
 * @property int $step
 * @property string $reset_period
 * @property string $calendar
 * @property string $period_key
 * @property int $current_value
 */
final class NumberSequence extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, TracksActor;

    protected $table = 'number_sequences';

    /** Increments are not configuration changes; manual adjustments are audited explicitly. */
    protected array $auditExclude = ['current_value', 'period_key'];

    protected $fillable = ['key', 'scope', 'form_id', 'field_id', 'pattern', 'prefix', 'padding', 'step', 'reset_period', 'calendar', 'period_key', 'current_value', 'last_adjusted_at'];

    protected function casts(): array
    {
        return ['padding' => 'integer', 'step' => 'integer', 'current_value' => 'integer', 'last_adjusted_at' => 'datetime'];
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'number_sequence';
    }
}
