<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $native_name
 * @property string $direction
 * @property string $calendar
 * @property string $digits
 * @property string $date_format
 * @property string $time_format
 * @property array<string, mixed> $number_format
 * @property int $first_day_of_week
 * @property int|null $fallback_locale_id
 * @property bool $is_enabled
 * @property bool $is_default
 * @property int $sort_order
 */
final class Locale extends BaseModel
{
    protected $fillable = ['code', 'native_name', 'direction', 'calendar', 'digits', 'date_format', 'time_format', 'number_format', 'first_day_of_week', 'fallback_locale_id', 'is_enabled', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['number_format' => 'array', 'is_enabled' => 'boolean', 'is_default' => 'boolean', 'first_day_of_week' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Locale, $this> */
    public function fallback(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_locale_id');
    }
}
