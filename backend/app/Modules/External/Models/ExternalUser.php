<?php

declare(strict_types=1);

namespace App\Modules\External\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * An external account (specification §4.32, ADR-0058): a customer,
 * supplier or applicant who registers, verifies the address, is approved
 * when the portal asks for it, and then signs in to the portal with an
 * external role. Never an internal user.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property Carbon|null $created_at
 * @property string $email
 * @property string $name
 * @property string $password
 * @property int $role_id
 * @property string $status
 * @property Carbon|null $email_verified_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $last_login_at
 * @property int $failed_login_count
 * @property Carbon|null $locked_until
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $deleted_at
 */
final class ExternalUser extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    public const STATUSES = ['pending_verification', 'pending_approval', 'approved', 'rejected', 'suspended'];

    protected $table = 'external_users';

    protected $hidden = ['password', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'email_verified_at' => 'datetime', 'approved_at' => 'datetime', 'last_login_at' => 'datetime',
            'locked_until' => 'datetime', 'anonymized_at' => 'datetime', 'deleted_at' => 'datetime', 'failed_login_count' => 'integer',
        ];
    }
}
