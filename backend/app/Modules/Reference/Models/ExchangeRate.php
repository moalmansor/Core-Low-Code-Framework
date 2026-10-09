<?php

declare(strict_types=1);

namespace App\Modules\Reference\Models;

use App\Modules\Audit\Auditable;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A historical exchange rate (base → quote) effective from a moment.
 *
 * @property int $id
 * @property int $base_currency_id
 * @property int $quote_currency_id
 * @property string $rate
 * @property Carbon $effective_at
 * @property string $source
 */
final class ExchangeRate extends BaseModel
{
    use Auditable, BelongsToOrganization, TracksActor;

    protected $fillable = ['base_currency_id', 'quote_currency_id', 'rate', 'effective_at', 'source'];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime'];
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'exchange_rate';
    }
}
