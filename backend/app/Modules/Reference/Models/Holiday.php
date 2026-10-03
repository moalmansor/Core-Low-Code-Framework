<?php

declare(strict_types=1);

namespace App\Modules\Reference\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\HasStableUuid;

/**
 * A holiday of a business calendar: one occurrence, or recurring every
 * Gregorian or Hijri (Umm al-Qura) year.
 *
 * @property int $id
 * @property string $uuid
 * @property int $business_calendar_id
 * @property string $starts_on
 * @property string $ends_on
 * @property string $recurrence
 * @property int|null $hijri_month
 * @property int|null $hijri_day
 */
final class Holiday extends BaseModel
{
    use HasStableUuid, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['business_calendar_id', 'starts_on', 'ends_on', 'recurrence', 'hijri_month', 'hijri_day'];

    protected function casts(): array
    {
        return ['hijri_month' => 'integer', 'hijri_day' => 'integer'];
    }

    public function translationType(): string
    {
        return 'holiday';
    }
}
