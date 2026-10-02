<?php

declare(strict_types=1);

namespace App\Modules\Core\Tenancy;

use App\Modules\Core\Models\Organization;
use RuntimeException;

/**
 * The organization the current request or job acts in (architecture §19.17).
 * Defaults to the platform organization; the authentication middleware switches
 * it to the signed-in user's organization.
 */
final class TenantContext
{
    private ?int $organizationId = null;

    private ?int $platformId = null;

    public function organizationId(): int
    {
        return $this->organizationId ?? $this->platformOrganizationId();
    }

    public function set(int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function reset(): void
    {
        $this->organizationId = null;
        $this->platformId = null;
    }

    public function platformOrganizationId(): int
    {
        if ($this->platformId === null) {
            $id = Organization::query()->withoutGlobalScopes()->where('is_platform', true)->value('id');
            if ($id === null) {
                throw new RuntimeException('The platform organization is missing; run the database seeders.');
            }
            $this->platformId = (int) $id;
        }

        return $this->platformId;
    }
}
