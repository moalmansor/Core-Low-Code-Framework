<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Notifications\Jobs\SendQueuedEmail;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The email queue (specification §4.2, §4.16): every email the system sends
 * is a row here before it leaves, sent by a job on the `mail` queue, retried
 * with backoff up to its attempt limit, and kept with the exact failure
 * reason. The Operations Center resends (as a new row linked to the first),
 * edits recipients and resends, or cancels.
 */
final class EmailQueue
{
    public const SOURCES = ['rule', 'action', 'automation', 'system', 'test', 'download', 'alert'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly CorrelationId $correlation,
        private readonly TenantContext $tenant,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{source: string, locale: string, to: list<string>, cc?: list<string>, bcc?: list<string>, subject: string, html: string, rule_id?: int|null, template_id?: int|null, form_id?: int|null, record_id?: int|null, attachments?: list<int>, on_behalf_of?: int|null, delay_minutes?: int|null, resent_from?: int|null}  $m
     */
    public function enqueue(array $m): int
    {
        $now = Carbon::now('UTC');
        $at = $now->copy()->addMinutes(max(0, (int) ($m['delay_minutes'] ?? 0)));
        $id = (int) DB::table('email_queue')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $this->tenant->organizationId(),
            'created_at' => $now->format('Y-m-d H:i:s.u'), 'updated_at' => $now->format('Y-m-d H:i:s.u'),
            'notification_rule_id' => $m['rule_id'] ?? null, 'email_template_id' => $m['template_id'] ?? null,
            'form_id' => $m['form_id'] ?? null, 'record_id' => $m['record_id'] ?? null, 'source' => $m['source'], 'locale' => $m['locale'],
            'to' => json_encode(array_values($m['to'])), 'cc' => ($m['cc'] ?? []) === [] ? null : json_encode(array_values($m['cc'])),
            'bcc' => ($m['bcc'] ?? []) === [] ? null : json_encode(array_values($m['bcc'])),
            'subject' => mb_substr($m['subject'], 0, 998), 'body_html' => $m['html'],
            'attachment_file_ids' => ($m['attachments'] ?? []) === [] ? null : json_encode(array_values($m['attachments'])),
            'status' => 'pending', 'attempts' => 0, 'max_attempts' => (int) $this->settings->get('notifications', 'email_max_attempts'),
            'next_attempt_at' => $at->format('Y-m-d H:i:s.u'), 'resent_from_id' => $m['resent_from'] ?? null,
            'on_behalf_of_user_id' => $m['on_behalf_of'] ?? null, 'correlation_id' => $this->correlation->get(),
        ]);
        if (($m['delay_minutes'] ?? 0) <= 0) {
            SendQueuedEmail::dispatch($id)->afterCommit();
        }

        return $id;
    }

    /** Sends one queued email; a failure schedules the next attempt or, at the limit, marks it failed. */
    public function send(int $id): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        // Claim it: only a pending row whose time has come is sent, once.
        $claimed = DB::table('email_queue')->where('id', $id)->where('status', 'pending')->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => $now]);
        if ($claimed === 0) {
            return;
        }
        $row = DB::table('email_queue')->where('id', $id)->first();
        try {
            $sent = Mail::html((string) $row->body_html, function (Message $message) use ($row): void {
                $message->to(json_decode((string) $row->to, true) ?: [])->subject((string) $row->subject);
                if ($row->cc !== null) {
                    $message->cc(json_decode((string) $row->cc, true) ?: []);
                }
                if ($row->bcc !== null) {
                    $message->bcc(json_decode((string) $row->bcc, true) ?: []);
                }
                foreach (json_decode((string) ($row->attachment_file_ids ?? '[]'), true) ?: [] as $fileId) {
                    $file = StoredFile::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereKey((int) $fileId)->first();
                    if ($file !== null) {
                        $message->attachData((string) Storage::disk($file->disk)->get($file->path), $file->original_name, ['mime' => $file->mime_type]);
                    }
                }
            });
            DB::table('email_queue')->where('id', $id)->update([
                'status' => 'sent', 'sent_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                'message_id' => $sent?->getMessageId() === null ? null : mb_substr((string) $sent->getMessageId(), 0, 255), 'last_error' => null, 'next_attempt_at' => null,
            ]);
        } catch (Throwable $e) {
            $attempts = (int) $row->attempts;
            $final = $attempts >= (int) $row->max_attempts;
            DB::table('email_queue')->where('id', $id)->update([
                'status' => $final ? 'failed' : 'pending',
                'last_error' => mb_substr($e->getMessage(), 0, 4000),
                'next_attempt_at' => $final ? null : Carbon::now('UTC')->addMinutes(min(240, 2 ** $attempts))->format('Y-m-d H:i:s.u'),
                'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
        }
    }

    /** Dispatches the emails whose time has come (delayed sends and retries); run every minute. */
    public function dispatchDue(): int
    {
        $ids = DB::table('email_queue')->where('status', 'pending')->where('next_attempt_at', '<=', Carbon::now('UTC')->format('Y-m-d H:i:s.u'))
            ->orderBy('next_attempt_at')->limit(500)->pluck('id');
        foreach ($ids as $id) {
            SendQueuedEmail::dispatch((int) $id);
        }

        return $ids->count();
    }

    /**
     * Sends a copy again as a new row (to new recipients when given).
     *
     * @param  array{to?: list<string>, cc?: list<string>, bcc?: list<string>}|null  $recipients
     */
    public function resend(object $row, ?array $recipients, int $userId): int
    {
        $id = $this->enqueue([
            'source' => (string) $row->source, 'locale' => (string) $row->locale,
            'to' => $recipients['to'] ?? (json_decode((string) $row->to, true) ?: []),
            'cc' => $recipients === null ? (json_decode((string) ($row->cc ?? '[]'), true) ?: []) : ($recipients['cc'] ?? []),
            'bcc' => $recipients === null ? (json_decode((string) ($row->bcc ?? '[]'), true) ?: []) : ($recipients['bcc'] ?? []),
            'subject' => (string) $row->subject, 'html' => (string) $row->body_html,
            'rule_id' => $row->notification_rule_id === null ? null : (int) $row->notification_rule_id,
            'template_id' => $row->email_template_id === null ? null : (int) $row->email_template_id,
            'form_id' => $row->form_id === null ? null : (int) $row->form_id, 'record_id' => $row->record_id === null ? null : (int) $row->record_id,
            'attachments' => json_decode((string) ($row->attachment_file_ids ?? '[]'), true) ?: [], 'resent_from' => (int) $row->id,
        ]);
        $this->audit->record('operations.email_resent', 'operations', null, 'email_queue', (int) $row->id, ['new' => $id, 'recipients_changed' => $recipients !== null], $userId);

        return $id;
    }

    public function cancel(object $row, int $userId): bool
    {
        $done = DB::table('email_queue')->where('id', $row->id)->where('status', 'pending')->update([
            'status' => 'cancelled', 'cancelled_by' => $userId, 'next_attempt_at' => null, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]) > 0;
        if ($done) {
            $this->audit->record('operations.email_cancelled', 'operations', null, 'email_queue', (int) $row->id, null, $userId);
        }

        return $done;
    }
}
