<?php

declare(strict_types=1);

namespace App\Modules\Operations;

use App\Modules\Actions\Jobs\RunAction;
use App\Modules\Audit\AuditWriter;
use App\Modules\Automation\AutomationEngine;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\DataQuality\Jobs\RunDataRepair;
use App\Modules\DataQuality\Jobs\RunDuplicateSweep;
use App\Modules\Downloads\DownloadJobs;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Channels\ChannelDeliveries;
use App\Modules\Platform\Webhooks;
use App\Modules\Records\Jobs\RunExportJob;
use App\Modules\Records\Jobs\RunImportJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Failed jobs (specification §4.2, ADR-0045), gathered from where each kind
 * of work keeps its own state: imports, exports, custom downloads, action
 * runs and data-quality scans (bulk operations), automation runs, scheduled
 * tasks, webhook calls made by actions and automations (whose retry is their
 * run's), webhook deliveries (outgoing ones resent from the log, incoming
 * calls shown for diagnosis) and the queue's own failed jobs. Each can be inspected, retried where that is safe, or
 * dismissed with a reason; the failed row itself is kept.
 */
final class FailedJobs
{
    public const KINDS = ['import', 'export', 'download', 'action', 'automation', 'scheduled_task', 'webhook', 'webhook_delivery', 'channel_delivery', 'extension', 'queue_job'];

    /** Failures older than this are not gathered (the scheduled clean-ups remove most of them). */
    private const LOOKBACK_DAYS = 30;

    private const PER_KIND = 500;

    /** @var array<int, array<string, mixed>|null> */
    private array $forms = [];

    /** @var array<int, string|null> */
    private array $users = [];

    public function __construct(private readonly Translator $translator, private readonly AuditWriter $audit, private readonly TenantContext $tenant) {}

    public function count(): int
    {
        return count($this->gather(null));
    }

    /**
     * @param  array{kind?: string|null, page?: int}  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters): array
    {
        $kind = in_array($filters['kind'] ?? null, self::KINDS, true) ? (string) $filters['kind'] : null;
        $all = $this->gather($kind);
        usort($all, static fn (array $a, array $b) => strcmp((string) $b['failed_at'], (string) $a['failed_at']));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return ['rows' => array_slice($all, ($page - 1) * 25, 25), 'total' => count($all)];
    }

    /** @return array<string, mixed>|null one failure with its full error */
    public function inspect(string $kind, int $id): ?array
    {
        foreach ($this->gather($kind) as $row) {
            if ($row['id'] === $id) {
                return $row + ['detail' => $this->detail($kind, $id)];
            }
        }

        return null;
    }

    public function retry(string $kind, int $id, User $operator): void
    {
        $row = $this->inspect($kind, $id);
        if ($row === null || ! $row['retryable']) {
            throw ValidationException::withMessages(['job' => __('operations.not_retryable')]);
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        switch ($kind) {
            case 'import':
                DB::table('import_jobs')->where('id', $id)->where('status', 'failed')->update(['status' => 'queued', 'error' => null, 'finished_at' => null, 'updated_at' => $now]);
                RunImportJob::dispatch($id);
                break;
            case 'export':
                DB::table('export_jobs')->where('id', $id)->where('status', 'failed')->update(['status' => 'queued', 'error' => null, 'updated_at' => $now]);
                RunExportJob::dispatch($id);
                break;
            case 'download':
                $job = DB::table('download_jobs')->where('id', $id)->first();
                if ($job !== null) {
                    app(DownloadJobs::class)->retry($job);
                }
                break;
            case 'action':
                $op = DB::table('bulk_operations')->where('id', $id)->first();
                if ($op === null) {
                    break;
                }
                // Records that failed run again; those already done are skipped by the run.
                $result = json_decode((string) ($op->result ?? 'null'), true) ?: ['records' => [], 'outputs' => []];
                $result['records'] = array_values(array_filter((array) ($result['records'] ?? []), static fn ($r) => (bool) ($r['ok'] ?? false)));
                DB::table('bulk_operations')->where('id', $id)->update([
                    'status' => 'queued', 'error' => null, 'failed_count' => 0, 'finished_at' => null, 'updated_at' => $now,
                    'result' => in_array($op->type, ['duplicate_sweep', 'orphan_scan', 'validation_sweep'], true) ? null : json_encode($result, JSON_UNESCAPED_UNICODE),
                ]);
                match ($op->type) {
                    'duplicate_sweep' => RunDuplicateSweep::dispatch($id),
                    'orphan_scan', 'validation_sweep' => RunDataRepair::dispatch($id),
                    default => RunAction::dispatch($id),
                };
                break;
            case 'automation':
                $run = DB::table('automation_runs')->where('id', $id)->first();
                if ($run !== null) {
                    app(AutomationEngine::class)->retry($run, $operator);
                }
                break;
            case 'scheduled_task':
                DB::table('scheduled_tasks')->where('id', $id)->update(['next_run_at' => $now, 'updated_at' => $now]);
                break;
            case 'webhook_delivery':
                app(Webhooks::class)->resend($id);
                break;
            case 'channel_delivery':
                app(ChannelDeliveries::class)->retry($id);
                break;
            case 'queue_job':
                $uuid = DB::table('failed_jobs')->where('id', $id)->value('uuid');
                if ($uuid !== null) {
                    Artisan::call('queue:retry', ['id' => [(string) $uuid]]);
                }
                break;
        }
        $this->audit->record('operations.job_retried', 'operations', null, $kind, $id, ['kind' => $kind], $operator->id, null, $row['form']['id'] ?? null);
    }

    public function dismiss(string $kind, int $id, string $reason, User $operator): void
    {
        $row = $this->inspect($kind, $id);
        if ($row === null) {
            throw ValidationException::withMessages(['job' => __('operations.not_found')]);
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('operations_dismissals')->insert([
            'organization_id' => $this->tenant->organizationId(), 'kind' => $kind, 'reference_id' => $id,
            'reason' => mb_substr($reason, 0, 2000), 'dismissed_by' => $operator->id, 'dismissed_at' => $now,
        ]);
        $this->audit->record('operations.job_dismissed', 'operations', null, $kind, $id, ['kind' => $kind, 'reason' => mb_substr($reason, 0, 500)], $operator->id, null, $row['form']['id'] ?? null);
    }

    /** @return list<array<string, mixed>> */
    private function gather(?string $only): array
    {
        $since = Carbon::now('UTC')->subDays(self::LOOKBACK_DAYS)->format('Y-m-d H:i:s.u');
        $dismissed = [];
        foreach (DB::table('operations_dismissals')->where('dismissed_at', '>=', $since)->get(['kind', 'reference_id']) as $d) {
            $dismissed[$d->kind.':'.$d->reference_id] = true;
        }
        $rows = [];
        $want = static fn (string $k) => $only === null || $only === $k;
        if ($want('import')) {
            foreach (DB::table('import_jobs')->where('status', 'failed')->where('updated_at', '>=', $since)->orderByDesc('id')->limit(self::PER_KIND)->get() as $j) {
                $rows[] = $this->row('import', (int) $j->id, (int) $j->form_id, $j->user_id, null, $j->error, $j->finished_at ?? $j->updated_at, true, 1);
            }
        }
        if ($want('export')) {
            foreach (DB::table('export_jobs')->where('status', 'failed')->where('updated_at', '>=', $since)->orderByDesc('id')->limit(self::PER_KIND)->get() as $j) {
                $rows[] = $this->row('export', (int) $j->id, (int) $j->form_id, $j->user_id, strtoupper((string) $j->format), $j->error, $j->updated_at, true, 1);
            }
        }
        if ($want('download')) {
            $q = DB::table('download_jobs as j')->join('download_profiles as p', 'p.id', '=', 'j.download_profile_id')
                ->where('j.status', 'failed')->where('j.updated_at', '>=', $since)->orderByDesc('j.id')->limit(self::PER_KIND);
            foreach ($q->get(['j.id', 'j.user_id', 'j.error', 'j.finished_at', 'j.updated_at', 'j.attempts', 'p.form_id', 'p.id as profile_id']) as $j) {
                $name = $this->translator->many('download_profile', [(int) $j->profile_id], ['name'])[(int) $j->profile_id]['name'] ?? null;
                $rows[] = $this->row('download', (int) $j->id, (int) $j->form_id, $j->user_id, $name, $j->error, $j->finished_at ?? $j->updated_at, true, (int) $j->attempts);
            }
        }
        if ($want('action')) {
            $q = DB::table('bulk_operations')->where('dry_run', false)->whereIn('status', ['failed', 'completed_with_errors'])->where('updated_at', '>=', $since)->orderByDesc('id')->limit(self::PER_KIND);
            foreach ($q->get() as $op) {
                $label = null;
                if ($op->action_id !== null) {
                    $key = DB::table('actions')->where('id', $op->action_id)->value('key');
                    $label = $this->translator->many('action', [(int) $op->action_id], ['label'])[(int) $op->action_id]['label'] ?? Translator::humanize((string) $key);
                }
                $error = $op->error ?? __('operations.records_failed', ['n' => (int) $op->failed_count]);
                $rows[] = $this->row('action', (int) $op->id, $op->form_id === null ? null : (int) $op->form_id, $op->created_by, $label ?? __('operations.operation.'.$op->type), $error, $op->finished_at ?? $op->updated_at, true, 1);
            }
        }
        if ($want('automation')) {
            $q = DB::table('automation_runs as r')->join('automations as a', 'a.id', '=', 'r.automation_id')
                ->whereIn('r.status', ['failed', 'partially_failed'])->where('r.is_test', false)->where('r.updated_at', '>=', $since)->orderByDesc('r.id')->limit(self::PER_KIND);
            foreach ($q->get(['r.id', 'r.error', 'r.finished_at', 'r.updated_at', 'r.attempts', 'r.triggered_by', 'a.id as automation_id', 'a.key', 'a.form_id']) as $r) {
                $name = $this->translator->many('automation', [(int) $r->automation_id], ['name'])[(int) $r->automation_id]['name'] ?? Translator::humanize((string) $r->key);
                $rows[] = $this->row('automation', (int) $r->id, (int) $r->form_id, $r->triggered_by, $name, $r->error ?? __('operations.steps_failed'), $r->finished_at ?? $r->updated_at, true, (int) $r->attempts);
            }
        }
        if ($want('scheduled_task')) {
            foreach (DB::table('scheduled_tasks')->where('last_status', 'failed')->where('last_run_at', '>=', $since)->orderByDesc('last_run_at')->limit(self::PER_KIND)->get() as $t) {
                $rows[] = $this->row('scheduled_task', (int) $t->id, null, null, (string) $t->name, $t->last_error, $t->last_run_at, $t->kind !== 'system' && (bool) $t->is_enabled, 1);
            }
        }
        if ($want('webhook')) {
            $q = DB::table('audit_logs')->whereIn('event', ['action.webhook_called', 'automation.webhook_called'])->where('occurred_at', '>=', $since)
                ->where('meta', 'like', '%"error":"%')->orderByDesc('occurred_at')->limit(self::PER_KIND);
            foreach ($q->get(['id', 'event', 'meta', 'form_id', 'actor_user_id', 'occurred_at']) as $a) {
                $meta = json_decode((string) $a->meta, true) ?: [];
                $subject = trim(($meta['method'] ?? '').' '.($meta['host'] ?? ''));
                $rows[] = $this->row('webhook', (int) $a->id, $a->form_id === null ? null : (int) $a->form_id, $a->actor_user_id, $subject, (string) ($meta['error'] ?? ''), $a->occurred_at, false, (int) ($meta['attempt'] ?? 1));
            }
        }
        if ($want('webhook_delivery')) {
            $q = DB::table('webhook_deliveries')->where('created_at', '>=', $since)->orderByDesc('id')->limit(self::PER_KIND)
                ->where(static fn ($q) => $q->where(static fn ($q) => $q->where('direction', 'outgoing')->whereIn('status', ['failed', 'blocked']))
                    ->orWhere(static fn ($q) => $q->where('direction', 'incoming')->whereIn('status', ['failed', 'signature_invalid'])));
            foreach ($q->get(['id', 'direction', 'webhook_id', 'inbound_endpoint_id', 'method', 'url', 'error', 'updated_at', 'attempt', 'response_status']) as $d) {
                $hook = $d->webhook_id === null ? null : DB::table('webhooks')->where('id', $d->webhook_id)->first(['form_id', 'created_by']);
                $endpoint = $d->inbound_endpoint_id === null ? null : DB::table('inbound_endpoints')->where('id', $d->inbound_endpoint_id)->first(['form_id', 'slug']);
                $subject = $d->direction === 'incoming' ? '← '.($endpoint->slug ?? parse_url((string) $d->url, PHP_URL_PATH)) : '→ '.$d->method.' '.parse_url((string) $d->url, PHP_URL_HOST);
                $formId = $hook->form_id ?? $endpoint->form_id ?? null;
                $rows[] = $this->row('webhook_delivery', (int) $d->id, $formId === null ? null : (int) $formId, null, $subject,
                    $d->error ?? ($d->response_status === null ? null : 'HTTP '.$d->response_status), $d->updated_at, $d->direction === 'outgoing' && $d->webhook_id !== null, max(1, (int) $d->attempt));
            }
        }
        if ($want('channel_delivery')) {
            $q = DB::table('notification_deliveries as d')->join('notification_channels as c', 'c.id', '=', 'd.notification_channel_id')
                ->where('d.status', 'failed')->where('d.updated_at', '>=', $since)->orderByDesc('d.id')->limit(self::PER_KIND);
            foreach ($q->get(['d.id', 'd.user_id', 'd.error', 'd.updated_at', 'd.attempts', 'd.payload', 'c.id as channel_id', 'c.key']) as $d) {
                $name = $this->translator->many('notification_channel', [(int) $d->channel_id], ['name'])[(int) $d->channel_id]['name'] ?? Translator::humanize((string) $d->key);
                $formId = (json_decode((string) $d->payload, true) ?: [])['form_id'] ?? null;
                $rows[] = $this->row('channel_delivery', (int) $d->id, is_int($formId) ? $formId : null, $d->user_id, $name, $d->error, $d->updated_at, true, max(1, (int) $d->attempts));
            }
        }
        if ($want('extension')) {
            $q = DB::table('audit_logs')->where('event', 'extension.failed')->where('occurred_at', '>=', $since)->orderByDesc('occurred_at')->limit(self::PER_KIND);
            foreach ($q->get(['id', 'meta', 'form_id', 'actor_user_id', 'occurred_at']) as $a) {
                $meta = json_decode((string) $a->meta, true) ?: [];
                $subject = trim(($meta['key'] ?? '').' v'.($meta['version'] ?? '').' · '.($meta['point'] ?? ''));
                $rows[] = $this->row('extension', (int) $a->id, $a->form_id === null ? null : (int) $a->form_id, $a->actor_user_id, $subject, (string) ($meta['error'] ?? ''), $a->occurred_at, false, 1);
            }
        }
        if ($want('queue_job')) {
            foreach (DB::table('failed_jobs')->where('failed_at', '>=', $since)->orderByDesc('id')->limit(self::PER_KIND)->get(['id', 'queue', 'payload', 'exception', 'failed_at']) as $f) {
                $name = (string) (json_decode((string) $f->payload, true)['displayName'] ?? '');
                $rows[] = $this->row('queue_job', (int) $f->id, null, null, class_basename($name).' · '.$f->queue, strtok((string) $f->exception, "\n") ?: '', $f->failed_at, true, 1);
            }
        }

        return array_values(array_filter($rows, static fn (array $r) => ! isset($dismissed[$r['kind'].':'.$r['id']])));
    }

    /** @return array<string, mixed> */
    private function row(string $kind, int $id, ?int $formId, mixed $userId, ?string $subject, ?string $error, mixed $failedAt, bool $retryable, int $attempts): array
    {
        $form = null;
        if ($formId !== null) {
            $this->forms[$formId] ??= (function () use ($formId): ?array {
                $f = DB::table('forms')->where('id', $formId)->first(['id', 'uuid', 'key']);

                return $f === null ? null : ['id' => (int) $f->id, 'uuid' => strtolower((string) $f->uuid),
                    'name' => $this->translator->many('form', [(int) $f->id], ['name'])[(int) $f->id]['name'] ?? Translator::humanize((string) $f->key)];
            })();
            $form = $this->forms[$formId];
        }
        $user = null;
        if ($userId !== null) {
            $this->users[(int) $userId] ??= DB::table('users')->where('id', (int) $userId)->value('name');
            $user = $this->users[(int) $userId];
        }

        return [
            'key' => $kind.':'.$id, 'kind' => $kind, 'id' => $id, 'form' => $form, 'subject' => $subject, 'user' => $user,
            'error' => $error === null ? null : mb_substr(self::mask($error), 0, 500), 'failed_at' => $failedAt === null ? null : Carbon::parse((string) $failedAt, 'UTC')->toIso8601ZuluString(),
            'retryable' => $retryable, 'attempts' => $attempts,
        ];
    }

    /** The whole error of one failure (the queue's stack trace, a run's step log). */
    private function detail(string $kind, int $id): ?string
    {
        $text = match ($kind) {
            'queue_job' => DB::table('failed_jobs')->where('id', $id)->value('exception'),
            'automation' => DB::table('automation_runs')->where('id', $id)->value('steps_log'),
            'action' => DB::table('bulk_operations')->where('id', $id)->value('result'),
            'scheduled_task' => DB::table('scheduled_tasks')->where('id', $id)->value('last_error'),
            'webhook_delivery' => DB::table('webhook_deliveries')->where('id', $id)->value('response_body'),
            'channel_delivery' => DB::table('notification_deliveries')->where('id', $id)->value('error'),
            default => null,
        };

        return $text === null ? null : mb_substr(self::mask((string) $text), 0, 60000);
    }

    private static function mask(string $text): string
    {
        return (string) preg_replace(['/(password|secret|token)=\S+/i', '/\b\d{12,19}\b/'], ['$1=«masked»', '«masked»'], $text);
    }
}
