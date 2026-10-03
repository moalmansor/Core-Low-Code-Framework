<?php

declare(strict_types=1);

namespace App\Modules\Schema\Execution;

use App\Modules\Schema\Models\PublishLock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Publish locks (architecture §12.4): a form and every form related to it are
 * locked together, all-or-nothing, in ascending id order. The `publish_locks`
 * row (unique `held_key`) is the source of truth; a Redis lock per form guards
 * the acquisition race. When any form is held, a `waiting` row records who
 * blocks the request.
 */
final class PublishLocks
{
    public const TTL_SECONDS = 1800;

    /**
     * @param  list<int>  $formIds
     * @return array{acquired: bool, token: string, blocked_by: PublishLock|null}
     */
    public function acquire(array $formIds, int $ownerUserId, ?int $planId): array
    {
        $formIds = array_values(array_unique($formIds));
        sort($formIds);
        $group = hash('sha256', implode(',', $formIds));
        $token = bin2hex(random_bytes(16));
        $guard = Cache::lock('publish-locks:'.$group, 10);
        if (! $guard->block(10)) {
            return ['acquired' => false, 'token' => $token, 'blocked_by' => null];
        }
        try {
            $this->expireStale();
            $blocker = PublishLock::query()->whereIn('form_id', $formIds)->where('status', 'held')->orderBy('id')->first();
            if ($blocker !== null) {
                PublishLock::query()->where('lock_group', $group)->where('owner_user_id', $ownerUserId)->where('status', 'waiting')->delete();
                foreach ($formIds as $formId) {
                    PublishLock::query()->create([
                        'form_id' => $formId, 'lock_group' => $group, 'status' => 'waiting', 'migration_plan_id' => $planId,
                        'owner_user_id' => $ownerUserId, 'blocked_by_lock_id' => $blocker->id,
                        'expires_at' => Carbon::now('UTC')->addSeconds(self::TTL_SECONDS),
                    ]);
                }

                return ['acquired' => false, 'token' => $token, 'blocked_by' => $blocker];
            }
            try {
                DB::transaction(function () use ($formIds, $group, $ownerUserId, $planId): void {
                    PublishLock::query()->where('lock_group', $group)->where('owner_user_id', $ownerUserId)->where('status', 'waiting')->delete();
                    $now = Carbon::now('UTC');
                    foreach ($formIds as $formId) {
                        PublishLock::query()->create([
                            'form_id' => $formId, 'lock_group' => $group, 'status' => 'held', 'migration_plan_id' => $planId,
                            'owner_user_id' => $ownerUserId, 'acquired_at' => $now, 'heartbeat_at' => $now,
                            'expires_at' => $now->copy()->addSeconds(self::TTL_SECONDS), 'held_key' => (string) $formId,
                        ]);
                    }
                });
            } catch (UniqueConstraintViolationException) {
                return ['acquired' => false, 'token' => $token, 'blocked_by' => PublishLock::query()->whereIn('form_id', $formIds)->where('status', 'held')->first()];
            }

            return ['acquired' => true, 'token' => $group, 'blocked_by' => null];
        } finally {
            $guard->release();
        }
    }

    public function heartbeat(string $group): void
    {
        $now = Carbon::now('UTC');
        PublishLock::query()->where('lock_group', $group)->where('status', 'held')
            ->update(['heartbeat_at' => $now, 'expires_at' => $now->copy()->addSeconds(self::TTL_SECONDS)]);
    }

    public function release(string $group): void
    {
        PublishLock::query()->where('lock_group', $group)->where('status', 'held')
            ->update(['status' => 'released', 'held_key' => null, 'updated_at' => Carbon::now('UTC')]);
    }

    /** Locks whose holder stopped sending heartbeats are expired so the forms are not blocked forever. */
    public function expireStale(): void
    {
        PublishLock::query()->where('status', 'held')->where('expires_at', '<', Carbon::now('UTC'))
            ->update(['status' => 'expired', 'held_key' => null]);
        PublishLock::query()->where('status', 'waiting')->where('expires_at', '<', Carbon::now('UTC'))->update(['status' => 'expired']);
    }

    public function heldFor(int $formId): ?PublishLock
    {
        $this->expireStale();

        return PublishLock::query()->where('form_id', $formId)->where('status', 'held')->first();
    }
}
