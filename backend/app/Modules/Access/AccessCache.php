<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Effective-permission cache with an access epoch per organization
 * (architecture §16.5). Any change to grants, roles, memberships, or
 * departments bumps the epoch after commit, which invalidates every cached
 * snapshot at once. The epoch is mirrored in `settings` (group `access`, key
 * `epoch`, never shown in the settings screens): if the cache loses the
 * counter, it resumes from the mirror instead of restarting at 1, so an old
 * snapshot can never match again.
 */
final class AccessCache
{
    private const MIRROR_GROUP = 'access';

    private const MIRROR_KEY = 'epoch';

    public function __construct(private readonly TenantContext $tenant) {}

    public function epoch(): int
    {
        $organizationId = $this->tenant->organizationId();

        return (int) Cache::rememberForever($this->epochKey(), fn (): int => $this->mirrored($organizationId));
    }

    public function bump(): void
    {
        $key = $this->epochKey();
        $organizationId = $this->tenant->organizationId();
        DB::afterCommit(function () use ($key, $organizationId): void {
            Cache::add($key, $this->mirrored($organizationId));
            $epoch = (int) Cache::increment($key);
            $this->mirror($organizationId, $epoch);
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

    private function mirrored(int $organizationId): int
    {
        $raw = DB::table('settings')->where(['organization_id' => $organizationId, 'group' => self::MIRROR_GROUP, 'key' => self::MIRROR_KEY])->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return max(1, (int) ($decoded['v'] ?? 1));
    }

    /** Stores the epoch when it is higher than the mirrored one (the mirror never goes back). */
    private function mirror(int $organizationId, int $epoch): void
    {
        if ($epoch <= $this->mirrored($organizationId)) {
            return;
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $where = ['organization_id' => $organizationId, 'group' => self::MIRROR_GROUP, 'key' => self::MIRROR_KEY];
        $value = (string) json_encode(['v' => $epoch]);
        if (DB::table('settings')->where($where)->update(['value' => $value, 'updated_at' => $now]) > 0) {
            return;
        }
        try {
            DB::table('settings')->insert($where + ['value' => $value, 'is_encrypted' => false, 'created_at' => $now, 'updated_at' => $now]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent bump created the row first; the next bump raises it further.
        }
    }
}
