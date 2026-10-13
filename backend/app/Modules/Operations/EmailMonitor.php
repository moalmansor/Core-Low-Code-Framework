<?php

declare(strict_types=1);

namespace App\Modules\Operations;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Notifications\EmailQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The Operations Center's e-mail queue monitor (specification §4.2): every
 * e-mail with its status — pending, sending, sent, failed, cancelled, or
 * stuck when it has waited (or been sending) longer than the configured
 * threshold — its recipients, subject, form, record and rule, attempts and
 * the exact failure reason; resend (to new recipients when changed), cancel
 * and the rendered HTML.
 */
final class EmailMonitor
{
    public const STATUSES = ['pending', 'sending', 'sent', 'failed', 'cancelled', 'stuck'];

    public function __construct(private readonly SettingsService $settings, private readonly Translator $translator, private readonly EmailQueue $queue) {}

    /**
     * @param  array{status?: string|null, search?: string|null, page?: int}  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters): array
    {
        $q = DB::table('email_queue');
        $status = $filters['status'] ?? null;
        if ($status === 'stuck') {
            $this->stuck($q);
        } elseif (is_string($status) && in_array($status, self::STATUSES, true)) {
            $q->where('status', $status);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 100)).'%';
            $q->where(static fn (Builder $w) => $w->where('subject', 'like', $like)->orWhere('to', 'like', $like)->orWhere('cc', 'like', $like));
        }
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage(max(1, (int) ($filters['page'] ?? 1)), 25)->get();

        return ['rows' => $this->present($rows->all()), 'total' => $total];
    }

    public function stuckCount(): int
    {
        $q = DB::table('email_queue');
        $this->stuck($q);

        return $q->count();
    }

    /** Pending past its due time by more than the threshold, or sending for longer than it. */
    public function stuck(Builder $q): void
    {
        $before = Carbon::now('UTC')->subMinutes((int) $this->settings->get('operations', 'stuck_email_minutes'))->format('Y-m-d H:i:s.u');
        $q->where(static fn (Builder $w) => $w->where(static fn (Builder $p) => $p->where('status', 'pending')->where('next_attempt_at', '<', $before))
            ->orWhere(static fn (Builder $s) => $s->where('status', 'sending')->where('updated_at', '<', $before)));
    }

    public function find(string $uuid): ?object
    {
        return DB::table('email_queue')->where('uuid', strtolower($uuid))->first();
    }

    /**
     * Resends each e-mail as a new queued copy (to new recipients when given,
     * for a single e-mail).
     *
     * @param  list<object>  $rows
     * @param  array{to?: list<string>, cc?: list<string>, bcc?: list<string>}|null  $recipients
     * @return list<string> the new copies
     */
    public function resend(array $rows, ?array $recipients, int $userId): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = $this->queue->resend($row, $recipients, $userId);
            $out[] = strtolower((string) DB::table('email_queue')->where('id', $id)->value('uuid'));
        }

        return $out;
    }

    /**
     * Cancels the pending ones; the others are left as they are.
     *
     * @param  list<object>  $rows
     */
    public function cancel(array $rows, int $userId): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $n += $this->queue->cancel($row, $userId) ? 1 : 0;
        }

        return $n;
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    public function present(array $rows): array
    {
        $forms = DB::table('forms')->whereIn('id', array_filter(array_map(static fn ($r) => $r->form_id, $rows)) ?: [0])->get(['id', 'uuid', 'key', 'table_name'])->keyBy('id');
        $formNames = $this->translator->many('form', $forms->keys()->map(static fn ($v) => (int) $v)->all(), ['name']);
        $ruleIds = array_values(array_filter(array_map(static fn ($r) => $r->notification_rule_id === null ? null : (int) $r->notification_rule_id, $rows)));
        $rules = DB::table('notification_rules')->whereIn('id', $ruleIds ?: [0])->pluck('key', 'id');
        $ruleNames = $this->translator->many('notification_rule', $ruleIds, ['name']);
        $stuckIds = [];
        if ($rows !== []) {
            $q = DB::table('email_queue')->whereIn('id', array_map(static fn ($r) => (int) $r->id, $rows));
            $this->stuck($q);
            $stuckIds = array_flip($q->pluck('id')->map(static fn ($v) => (int) $v)->all());
        }

        return array_map(function (object $r) use ($forms, $formNames, $rules, $ruleNames, $stuckIds): array {
            $form = $r->form_id === null ? null : ($forms[$r->form_id] ?? null);
            $recordUuid = $form === null || $r->record_id === null || (string) $form->table_name === '' ? null : DB::table((string) $form->table_name)->where('id', $r->record_id)->value('uuid');

            return [
                'uuid' => strtolower((string) $r->uuid), 'status' => isset($stuckIds[(int) $r->id]) ? 'stuck' : $r->status, 'raw_status' => $r->status,
                'to' => json_decode((string) $r->to, true) ?: [], 'cc' => json_decode((string) ($r->cc ?? '[]'), true) ?: [], 'bcc' => json_decode((string) ($r->bcc ?? '[]'), true) ?: [],
                'subject' => (string) $r->subject, 'source' => $r->source,
                'form' => $form === null ? null : ['uuid' => strtolower((string) $form->uuid), 'name' => $formNames[(int) $form->id]['name'] ?? Translator::humanize((string) $form->key)],
                'record' => $recordUuid === null ? null : strtolower((string) $recordUuid),
                'rule' => $r->notification_rule_id === null ? null : ($ruleNames[(int) $r->notification_rule_id]['name'] ?? Translator::humanize((string) ($rules[$r->notification_rule_id] ?? ''))),
                'attempts' => (int) $r->attempts, 'max_attempts' => (int) $r->max_attempts, 'error' => $r->last_error,
                'created_at' => self::iso($r->created_at), 'sent_at' => self::iso($r->sent_at), 'next_attempt_at' => self::iso($r->next_attempt_at),
                'resent' => $r->resent_from_id !== null,
            ];
        }, $rows);
    }

    private static function iso(mixed $at): ?string
    {
        return $at === null ? null : Carbon::parse((string) $at, 'UTC')->toIso8601ZuluString();
    }
}
