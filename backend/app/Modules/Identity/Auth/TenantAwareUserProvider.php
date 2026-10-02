<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the session's user by primary key regardless of the current tenant:
 * the tenant is derived from that user (SetTenantFromUser), so the lookup
 * cannot itself be scoped by it. Lookups by credentials (login, password
 * reset) stay scoped to the current organization.
 */
final class TenantAwareUserProvider extends EloquentUserProvider
{
    /** @return (Authenticatable&Model)|null */
    public function retrieveById($identifier): ?Authenticatable
    {
        $model = $this->createModel();

        /** @var (Authenticatable&Model)|null */
        return $this->newModelQuery($model)->withoutGlobalScope('organization')
            ->where($model->getAuthIdentifierName(), $identifier)->first();
    }

    /** @return (Authenticatable&Model)|null */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        $model = $this->createModel();
        $user = $this->newModelQuery($model)->withoutGlobalScope('organization')
            ->where($model->getAuthIdentifierName(), $identifier)->first();
        if ($user === null) {
            return null;
        }
        $remember = $user->getRememberToken();

        /** @var (Authenticatable&Model)|null */
        return $remember && hash_equals($remember, $token) ? $user : null;
    }
}
