<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Assignment\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Views\RelationPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who a notification rule, an action's message or an automation's message
 * reaches (specification §4.16), shared by all three so each offers the same
 * choices: named users, roles and departments; people held in the record —
 * a user, role or department picker or an email field of the form or of a
 * form it links to (a relation path of up to four hops); the record's
 * current assignee; whoever last changed its status; its creator; for
 * actions, the person running it; and outside addresses. A recipient is
 * stored as `{type, ref}` and resolved when the message is sent, never when
 * the rule is saved, so it always follows the record as it is then.
 */
final class Recipients
{
    public const TYPES = ['user', 'role', 'department', 'field', 'assignee', 'status_changer', 'creator', 'actor', 'static'];

    /** Field types whose value names people: picker storage => what it holds. */
    public const FIELD_KINDS = ['user' => 'user', 'role' => 'role', 'department' => 'department'];

    public function __construct(
        private readonly Membership $members,
        private readonly RelationPaths $paths,
    ) {}

    /**
     * A recipient as stored, or null when it is not valid for the form.
     *
     * @param  bool  $actor  whether "the person running it" may be chosen (actions)
     * @param  bool  $static  whether outside addresses may be chosen (email only)
     * @return array{type: string, ref: string|null}|null
     */
    public function normalize(?FormRuntime $rt, mixed $item, bool $actor = false, bool $static = true): ?array
    {
        if (! is_array($item)) {
            return null;
        }
        $type = $item['type'] ?? null;
        $ref = isset($item['ref']) && is_string($item['ref']) ? trim($item['ref']) : null;

        return match ($type) {
            // Checked for shape first: SQL Server rejects a non-UUID compared with a uniqueidentifier column.
            'user', 'role', 'department' => $ref !== null && Str::isUuid($ref) && $this->members->idOf($type, strtolower($ref)) !== null ? ['type' => $type, 'ref' => strtolower($ref)] : null,
            'field' => $rt !== null && $ref !== null && $this->fieldKind($rt, $ref) !== null ? ['type' => 'field', 'ref' => $ref] : null,
            'assignee', 'status_changer', 'creator' => ['type' => $type, 'ref' => null],
            'actor' => $actor ? ['type' => 'actor', 'ref' => null] : null,
            'static' => $static && $ref !== null && filter_var($ref, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($ref) <= 255 ? ['type' => 'static', 'ref' => mb_strtolower($ref)] : null,
            default => null,
        };
    }

    /**
     * What a field (or relation path) holds when it can name recipients:
     * `user`, `role`, `department` or `email`; null for anything else.
     */
    public function fieldKind(FormRuntime $rt, string $ref): ?string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,47}(\.[a-z][a-z0-9_]{0,47}){0,3}$/', $ref) !== 1) {
            return null;
        }
        $r = $this->paths->resolve($rt, explode('.', $ref));
        if ($r === null || $r['field'] === null) {
            return null;
        }
        $type = $r['rt']->type($r['field']);
        if ($type?->key === 'email') {
            return 'email';
        }

        return self::FIELD_KINDS[$type->storage ?? ''] ?? null;
    }

    /**
     * The users (by slot) and outside addresses the recipients name for a
     * record now. An address held in an email field that belongs to an
     * active user reaches that user, in their language and with their own
     * access; any other address is treated as an outside address.
     *
     * @param  array<string, mixed>  $spec  `{to, cc, bcc}` lists of recipients
     * @return array{to: list<int>, cc: list<int>, bcc: list<int>, static: array{to: list<string>, cc: list<string>, bcc: list<string>}}
     */
    public function resolve(FormRuntime $rt, int $recordId, array $spec, ?User $actor = null): array
    {
        $out = ['to' => [], 'cc' => [], 'bcc' => [], 'static' => ['to' => [], 'cc' => [], 'bcc' => []]];
        foreach (['to', 'cc', 'bcc'] as $slot) {
            foreach ((array) ($spec[$slot] ?? []) as $item) {
                $type = is_array($item) ? ($item['type'] ?? null) : null;
                $ref = is_array($item) && is_string($item['ref'] ?? null) ? $item['ref'] : null;
                [$ids, $addresses] = match ($type) {
                    'user', 'role', 'department' => [$ref !== null && Str::isUuid($ref) && ($id = $this->members->idOf($type, strtolower($ref))) !== null ? $this->members->userIds($type, $id) : [], []],
                    'field' => $ref === null ? [[], []] : $this->fromField($rt, $recordId, $ref),
                    'assignee' => [$this->assignees($rt, $recordId), []],
                    'status_changer' => [$this->active(array_filter([(int) DB::table('status_history')->where('form_id', $rt->form->id)->where('record_id', $recordId)->orderByDesc('acted_at')->orderByDesc('id')->value('acted_by')])), []],
                    'creator' => [$this->active(array_filter([(int) DB::table($rt->table)->where('id', $recordId)->value('created_by')])), []],
                    'actor' => [$actor === null ? [] : $this->active([$actor->id]), []],
                    'static' => [[], $ref === null ? [] : [mb_strtolower($ref)]],
                    default => [[], []],
                };
                array_push($out[$slot], ...$ids);
                array_push($out['static'][$slot], ...$addresses);
            }
            $out[$slot] = array_values(array_unique(array_map('intval', $out[$slot])));
            $out['static'][$slot] = array_values(array_unique($out['static'][$slot]));
        }

        return $out;
    }

    /**
     * People a field of the record (or of the records it links to) names now,
     * read with server authority: who to notify is never limited by what the
     * person who triggered it may see.
     *
     * @return array{0: list<int>, 1: list<string>} users and outside addresses
     */
    private function fromField(FormRuntime $rt, int $recordId, string $ref): array
    {
        $kind = $this->fieldKind($rt, $ref);
        if ($kind === null) {
            return [[], []];
        }
        $values = array_values(array_filter($this->paths->rawValues($rt, explode('.', $ref), [$recordId])[$recordId] ?? [], 'is_string'));
        if ($kind === 'email') {
            $addresses = array_values(array_unique(array_filter(array_map(static fn (string $v) => mb_strtolower(trim($v)), $values), static fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false)));
            $users = $addresses === [] ? collect() : DB::table('users')->whereIn('email', $addresses)->where('status', 'active')->whereNull('deleted_at')->get(['id', 'email']);
            $known = $users->map(static fn ($u) => mb_strtolower((string) $u->email))->all();

            return [$users->pluck('id')->map(static fn ($v) => (int) $v)->all(), array_values(array_diff($addresses, $known))];
        }
        $ids = [];
        foreach (array_unique(array_map('strtolower', array_filter($values, static fn (string $v) => Str::isUuid($v)))) as $uuid) {
            $id = $this->members->idOf($kind, $uuid);
            if ($id !== null) {
                array_push($ids, ...$this->members->userIds($kind, $id));
            }
        }

        return [$ids, []];
    }

    /** @return list<int> the people the record's active assignments name (a role or department: its members) */
    private function assignees(FormRuntime $rt, int $recordId): array
    {
        $ids = [];
        foreach (DB::table('assignments')->where('form_id', $rt->form->id)->where('record_id', $recordId)->where('status', 'active')->get(['assignee_type', 'assignee_id']) as $a) {
            array_push($ids, ...$this->members->userIds((string) $a->assignee_type, (int) $a->assignee_id));
        }

        return $ids;
    }

    /** @param  list<int>  $ids @return list<int> those of them who are active users */
    private function active(array $ids): array
    {
        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->where('status', 'active')->whereNull('deleted_at')->pluck('id')->map(static fn ($v) => (int) $v)->all();
    }
}
