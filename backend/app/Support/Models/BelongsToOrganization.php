<?php

declare(strict_types=1);

namespace App\Support\Models;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant isolation (architecture §19.17): every query is scoped to the current
 * organization and new rows are stamped with it.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', static function (Builder $query): void {
            $query->where($query->getModel()->getTable().'.organization_id', app(TenantContext::class)->organizationId());
        });
        static::creating(static function (self $model): void {
            if (empty($model->getAttribute('organization_id'))) {
                $model->setAttribute('organization_id', app(TenantContext::class)->organizationId());
            }
        });
    }
}
