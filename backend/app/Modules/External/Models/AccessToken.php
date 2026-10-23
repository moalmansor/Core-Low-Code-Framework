<?php

declare(strict_types=1);

namespace App\Modules\External\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A tokenized single-record link (specification §4.32, ADR-0058): one
 * record, one action, an expiry and a number of uses, revocable. Only the
 * SHA-256 of the token is stored; the token itself exists only in the link.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property Carbon|null $created_at
 * @property int|null $created_by
 * @property string $token_hash
 * @property string $purpose
 * @property int $form_id
 * @property int $record_id
 * @property string $action
 * @property string|null $section_key
 * @property string|null $recipient_email
 * @property int $max_uses
 * @property int $uses
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property int|null $transition_id
 */
final class AccessToken extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $table = 'access_tokens';

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime', 'max_uses' => 'integer', 'uses' => 'integer'];
    }
}
