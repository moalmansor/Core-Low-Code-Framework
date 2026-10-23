<?php

declare(strict_types=1);

namespace App\Modules\External\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A form published outside (specification §4.32, ADR-0058): its own address
 * `/x/f/{slug}`, theme, status on submission, the fields it shows, who may
 * open it, and its protections. Title, introduction and success message
 * are translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $form_id
 * @property string $slug
 * @property int|null $theme_id
 * @property int|null $initial_status_id
 * @property list<string> $exposed_fields
 * @property bool $captcha
 * @property int $rate_limit_per_ip
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property array{from: string, to: string, days?: list<int>}|null $daily_window
 * @property int|null $submission_cap
 * @property int $submissions_count
 * @property string $verification
 * @property string|null $password_hash
 * @property int $run_as_user_id
 * @property bool $is_active
 * @property string $audience
 * @property list<int>|null $role_ids
 */
final class ExternalForm extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['title', 'intro', 'success_message'];

    protected $table = 'external_forms';

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'exposed_fields' => 'array', 'daily_window' => 'array', 'role_ids' => 'array', 'captcha' => 'boolean', 'is_active' => 'boolean',
            'opens_at' => 'datetime', 'closes_at' => 'datetime', 'rate_limit_per_ip' => 'integer', 'submission_cap' => 'integer', 'submissions_count' => 'integer',
        ];
    }

    public function translationType(): string
    {
        return 'external_form';
    }
}
