<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $locale
 * @property string|null $timezone
 * @property string|null $calendar
 * @property string|null $date_format
 * @property string|null $digits
 * @property string $theme_mode
 * @property string|null $density
 * @property string $digest_frequency
 */
final class UserPreference extends BaseModel
{
    protected $table = 'user_preferences';

    protected $fillable = ['locale', 'timezone', 'calendar', 'date_format', 'number_format', 'digits', 'theme_mode', 'density', 'digest_frequency'];

    protected $attributes = ['theme_mode' => 'system', 'digest_frequency' => 'none'];

    protected function casts(): array
    {
        return [
            'number_format' => 'array',
            'notification_channels' => 'array',
            'landing_page' => 'array',
            'pinned_records' => 'array',
            'shortcuts' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
