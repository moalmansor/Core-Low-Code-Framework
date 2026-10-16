<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use Illuminate\Support\Carbon;

/**
 * A personal API token (specification §4.22, ADR-0050). Only the SHA-256 of
 * the secret is stored; the secret is shown once, when the token is made.
 * A token acts as its owner and can never do more than the owner may: its
 * abilities (`form:{uuid}:read`, `form:{uuid}:write`) narrow the owner's
 * permissions, they never add to them.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property list<string> $abilities
 * @property list<string>|null $ip_allowlist
 * @property int|null $rate_limit_per_minute
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property Carbon $created_at
 */
final class ApiToken extends BaseModel
{
    use BelongsToOrganization, HasStableUuid;

    protected $table = 'api_tokens';

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array', 'ip_allowlist' => 'array', 'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime',
        ];
    }

    public function active(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Whether the token may read (or, with $write, change) records of a form. */
    public function allowsForm(string $formUuid, bool $write): bool
    {
        $abilities = $this->abilities;
        $form = strtolower($formUuid);

        return in_array("form:{$form}:write", $abilities, true) || (! $write && in_array("form:{$form}:read", $abilities, true));
    }
}
