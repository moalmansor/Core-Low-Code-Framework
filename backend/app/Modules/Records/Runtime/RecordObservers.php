<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Identity\Models\User;

/**
 * Lets other modules add to a record's `record.saved` event while the values
 * before and after the change are still at hand, without keeping old values
 * in the outbox. Notifications and automations use it to note which of their
 * conditions became true with this save (a condition that held before does
 * not fire again).
 */
final class RecordObservers
{
    /** @var list<callable(FormRuntime, array<string, mixed>|null, array<string, mixed>, User): array<string, mixed>> */
    private array $observers = [];

    /** @param  callable(FormRuntime, array<string, mixed>|null, array<string, mixed>, User): array<string, mixed>  $observer */
    public function observe(callable $observer): void
    {
        $this->observers[] = $observer;
    }

    /**
     * @param  array<string, mixed>|null  $old  values before the save (null on create)
     * @param  array<string, mixed>  $new
     * @return array<string, mixed> what the observers add to the event
     */
    public function payload(FormRuntime $rt, ?array $old, array $new, User $user): array
    {
        $out = [];
        foreach ($this->observers as $observer) {
            $out = array_merge_recursive($out, $observer($rt, $old, $new, $user));
        }

        return $out;
    }
}
