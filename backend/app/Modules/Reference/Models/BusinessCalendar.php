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
 * Reference data (specification §4.34, architecture §10.15): `business_calendars`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $key
 * @property string $timezone
 * @property list<int> $working_days
 * @property list<array{day: int, start: string, end: string}> $working_hours
 * @property string|null $country_code
 * @property bool $is_default
 */
final class BusinessCalendar extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    public function translationType(): string
    {
        return 'business_calendar';
    }

    protected $table = 'business_calendars';

    protected $fillable = ['key', 'timezone', 'working_days', 'working_hours', 'country_code', 'is_default'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'working_hours' => 'array', 'is_default' => 'boolean'];
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'business_calendar';
    }
}
