<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Effective-permission cache with an access epoch per organization
 * (architecture §16.5). Any change to grants, roles, memberships, or
 * departments bumps the epoch after commit, which invalidates every cached
 * snapshot at once.
 */
final class AccessCache
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function epoch(): int
    {
        return (int) Cache::rememberForever($this->epochKey(), static fn (): int => 1);
    }

    public function bump(): void
    {
        $key = $this->epochKey();
        DB::afterCommit(static function () use ($key): void {
            Cache::add($key, 1);
            Cache::increment($key);
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return T
     */
    public function remember(int $userId, callable $compute): mixed
    {
        return Cache::remember(
            sprintf('acc:%d:%d:%d', $this->tenant->organizationId(), $userId, $this->epoch()),
            3600,
            $compute,
        );
    }

    private function epochKey(): string
    {
        return 'acc:epoch:'.$this->tenant->organizationId();
    }
}
