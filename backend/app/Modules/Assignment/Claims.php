<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Claim and release on role and department queues (specification §4.25,
 * architecture §19.4). A claim holds `active_key = "{form}:{record}"`, which
 * is unique, so a second concurrent claim fails on the index. While a record
 * is claimed, only the claimer (or a holder of Reassign Records) may change it.
 */
final class Claims
{
    public function __construct(
        private readonly Membership $members,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
    ) {}

    /** Claims the record's queue assignment for the user. */
    public function claim(FormRuntime $rt, int $recordId, User $user): array
    {
        $subjects = $this->members->subjectsOf($user);
        $assignment = DB::table('assignments')->where('form_id', $rt->form->id)->where('record_id', $recordId)->where('status', 'active')
            ->whereIn('assignee_type', ['role', 'department'])->whereNull('approval_request_id')->orderByDesc('id')->get()
            ->first(static fn ($a) => in_array((int) $a->assignee_id, $subjects[$a->assignee_type] ?? [], true));
        if ($assignment === null) {
            throw new RecordException(403, 'not_in_queue', __('assignment.not_in_queue'));
        }
        $queue = DB::table('queues')->where('organization_id', $rt->form->organization_id)->where('is_active', true)
            ->where('type', $assignment->assignee_type)->where($assignment->assignee_type === 'role' ? 'role_id' : 'department_id', $assignment->assignee_id)
            ->whereIn('id', DB::table('queue_forms')->where('form_id', $rt->form->id)->select('queue_id'))->value('id');
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        try {
            DB::table('queue_claims')->insert([
                'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now,
                'queue_id' => $queue, 'assignment_id' => $assignment->id, 'form_id' => $rt->form->id, 'record_id' => $recordId,
                'claimed_by' => $user->id, 'claimed_at' => $now, 'released_at' => null, 'release_reason' => null,
                'active_key' => $this->key($rt->form->id, $recordId),
            ]);
        } catch (UniqueConstraintViolationException) {
            $holder = $this->holder($rt->form->id, $recordId);
            if ($holder !== null && (int) $holder->claimed_by === $user->id) {
                return $this->present($holder);
            }
            throw new RecordException(409, 'already_claimed', __('assignment.already_claimed', ['name' => $holder === null ? '' : (string) DB::table('users')->where('id', $holder->claimed_by)->value('name')]));
        }
        $this->audit->record('record.claimed', 'workflow', null, 'record', $recordId, ['form' => $rt->form->key], $user->id, null, $rt->form->id, $recordId);

        return $this->present($this->holder($rt->form->id, $recordId));
    }

    /** Releases the user's claim (or anyone's, for holders of Reassign Records). */
    public function release(FormRuntime $rt, int $recordId, User $user): void
    {
        $holder = $this->holder($rt->form->id, $recordId);
        if ($holder === null) {
            return;
        }
        $admin = (int) $holder->claimed_by !== $user->id;
        if ($admin && ! $this->access->allows($user, 'system.reassign_records')) {
            throw new RecordException(403, 'forbidden', __('assignment.not_your_claim'));
        }
        $this->end((int) $holder->id, $admin ? 'admin' : 'released');
        $this->audit->record('record.released', 'workflow', null, 'record', $recordId, ['form' => $rt->form->key, 'reason' => $admin ? 'admin' : 'released'], $user->id, null, $rt->form->id, $recordId);
    }

    /** Ends any active claim of the record (completed, reassigned). */
    public function releaseAll(int $formId, int $recordId, string $reason): void
    {
        $holder = $this->holder($formId, $recordId);
        if ($holder !== null) {
            $this->end((int) $holder->id, $reason);
        }
    }

    /** Rejects a change by someone other than the claimer (423), unless they may reassign. */
    public function guard(FormRuntime $rt, int $recordId, User $user): void
    {
        $holder = $this->holder($rt->form->id, $recordId);
        if ($holder !== null && (int) $holder->claimed_by !== $user->id && ! $this->access->allows($user, 'system.reassign_records')) {
            throw new RecordException(423, 'claimed', __('assignment.claimed_by', ['name' => (string) DB::table('users')->where('id', $holder->claimed_by)->value('name')]));
        }
    }

    /** Releases claims older than their queue's timeout. Returns how many. */
    public function expire(): int
    {
        $n = 0;
        foreach (DB::table('queue_claims')->join('queues', 'queues.id', '=', 'queue_claims.queue_id')->whereNotNull('queue_claims.active_key')
            ->whereNotNull('queues.claim_timeout_minutes')->get(['queue_claims.id', 'queue_claims.claimed_at', 'queues.claim_timeout_minutes']) as $c) {
            if (Carbon::parse($c->claimed_at, 'UTC')->addMinutes((int) $c->claim_timeout_minutes)->isPast()) {
                $this->end((int) $c->id, 'timeout');
                $n++;
            }
        }

        return $n;
    }

    public function holder(int $formId, int $recordId): ?object
    {
        return DB::table('queue_claims')->where('active_key', $this->key($formId, $recordId))->first();
    }

    /** @return array<string, mixed>|null */
    public function present(?object $claim): ?array
    {
        if ($claim === null) {
            return null;
        }
        $user = DB::table('users')->where('id', $claim->claimed_by)->first(['uuid', 'name']);

        return ['by' => ['uuid' => strtolower((string) $user?->uuid), 'name' => $user?->name], 'at' => Carbon::parse($claim->claimed_at, 'UTC')->toIso8601ZuluString()];
    }

    private function end(int $id, string $reason): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('queue_claims')->where('id', $id)->update(['active_key' => null, 'released_at' => $now, 'release_reason' => $reason, 'updated_at' => $now]);
    }

    private function key(int $formId, int $recordId): string
    {
        return $formId.':'.$recordId;
    }
}
