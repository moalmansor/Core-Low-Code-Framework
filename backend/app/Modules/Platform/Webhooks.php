<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\EgressViolation;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Jobs\DeliverWebhook;
use App\Modules\Platform\Models\Webhook;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Outgoing webhooks (specification §4.22, ADR-0050).
 *
 * - Committed record events become one delivery per subscribed, active
 *   webhook. The payload is the record as the webhook's owner (the last
 *   person who saved it) may see it: their record access and field access
 *   apply, and a record they may not see is logged as blocked, never sent.
 * - Every request goes through the egress gateway and is signed:
 *   `X-LCF-Signature: sha256=HMAC(secret, timestamp + "." + body)` with
 *   `X-LCF-Timestamp`, plus `X-LCF-Event` and `X-LCF-Delivery`.
 * - A failure (network, 5xx, 429) is retried after 1, 5, 30, 120 and 360
 *   minutes, up to the webhook's own retry count; any other answer is final.
 *   A refused address is never retried.
 * - The delivery log keeps the request with the signature and custom header
 *   values masked and sensitive fields hidden, the answer (truncated), the
 *   time it took and the attempt; failures appear in the Operations Center.
 */
final class Webhooks
{
    public const EVENTS = ['record.created', 'record.updated', 'record.deleted', 'record.status_changed'];

    public const BACKOFF_MINUTES = [1, 5, 30, 120, 360];

    private const MAX_REQUEST_LOG = 65_536;

    private const MAX_RESPONSE_LOG = 16_384;

    public function __construct(
        private readonly EgressGateway $egress,
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordScope $scope,
        private readonly AccessResolver $access,
    ) {}

    /** The webhook event of a committed record event, or null when none applies. @param  array<string, mixed>  $payload */
    public static function eventOf(string $outboxType, array $payload): ?string
    {
        return match ($outboxType) {
            'record.saved' => ($payload['operation'] ?? null) === 'create' ? 'record.created' : 'record.updated',
            'record.transitioned' => 'record.status_changed',
            'record.deleted' => 'record.deleted',
            default => null,
        };
    }

    /**
     * One delivery per subscribed webhook, queued with its payload.
     *
     * @param  array<string, mixed>  $payload
     * @return list<int> the delivery ids
     */
    public function fanOut(string $outboxType, array $payload, int $organizationId): array
    {
        $event = self::eventOf($outboxType, $payload);
        $form = $event === null ? null : Form::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('uuid', strtolower((string) ($payload['form'] ?? '')))->first();
        if ($event === null || $form === null) {
            return [];
        }
        $ids = [];
        $hooks = Webhook::query()->withoutGlobalScope('organization')->where('organization_id', $organizationId)->where('is_active', true)
            ->where(static fn ($q) => $q->whereNull('form_id')->orWhere('form_id', $form->id))->get();
        foreach ($hooks as $hook) {
            if (in_array($event, $hook->events, true)) {
                $ids[] = $this->queue($hook, $event, $this->body($hook, $event, $form, $payload));
            }
        }

        return $ids;
    }

    /** A ping, sent at once, so an admin can check the receiver. Returns the delivery. */
    public function test(Webhook $hook): int
    {
        $body = ['event' => 'webhook.test', 'occurred_at' => now()->toIso8601String(), 'webhook' => ['uuid' => $hook->uuid, 'key' => $hook->key]];
        $id = $this->log($hook, $body, null);
        $this->deliver($id, 'webhook.test', $this->json($body), false);

        return $id;
    }

    /**
     * Send a delivery again from the log, as a new delivery. The payload is
     * built from the record as it is now (the log keeps only a masked copy).
     */
    public function resend(int $deliveryId): int
    {
        $d = DB::table('webhook_deliveries')->where('id', $deliveryId)->where('direction', 'outgoing')->first();
        abort_if($d === null || $d->webhook_id === null, 404);
        /** @var Webhook $hook */
        $hook = Webhook::query()->findOrFail($d->webhook_id);
        $old = json_decode((string) $d->request_body, true) ?: [];
        $event = (string) ($old['event'] ?? 'webhook.test');
        if ($event === 'webhook.test') {
            return $this->test($hook);
        }
        $form = Form::query()->where('uuid', strtolower((string) ($old['form']['uuid'] ?? '')))->first();
        abort_if($form === null, 404);
        $body = $this->body($hook, $event, $form, ['record' => $old['record']['uuid'] ?? null] + ($old['transition'] ?? []));
        $body['redelivery_of'] = strtolower((string) $d->uuid);

        return $this->queue($hook, $event, $body);
    }

    /**
     * Remove delivery log rows older than each organization's
     * `api.webhook_delivery_days`; rows still waiting for a retry stay.
     */
    public function purge(): int
    {
        $removed = 0;
        $tenant = app(TenantContext::class);
        $settings = app(SettingsService::class);
        foreach (DB::table('organizations')->pluck('id') as $org) {
            $tenant->set((int) $org);
            $before = now()->subDays(max(1, (int) $settings->get('api', 'webhook_delivery_days')))->format('Y-m-d H:i:s.u');
            do {
                $ids = DB::table('webhook_deliveries')->where('organization_id', $org)->where('created_at', '<', $before)
                    ->whereNotIn('status', ['pending', 'retrying'])->limit(1000)->pluck('id')->all();
                $removed += $ids === [] ? 0 : DB::table('webhook_deliveries')->whereIn('id', $ids)->delete();
            } while (count($ids) === 1000);
        }

        return $removed;
    }

    /** Send one delivery; on a retryable failure schedule the next attempt with the same payload. */
    public function deliver(int $deliveryId, string $event, string $json, bool $scheduleRetry = true): void
    {
        $d = DB::table('webhook_deliveries')->where('id', $deliveryId)->first();
        if ($d === null || ! in_array($d->status, ['pending', 'retrying'], true)) {
            return;
        }
        /** @var Webhook|null $hook */
        $hook = Webhook::query()->withoutGlobalScope('organization')->find($d->webhook_id);
        if ($hook === null) {
            return;
        }
        $attempt = (int) $d->attempt + 1;
        $ts = (string) time();
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'LowCodeFramework-Webhook/1', 'X-LCF-Event' => $event, 'X-LCF-Delivery' => strtolower((string) $d->uuid),
            'X-LCF-Timestamp' => $ts, 'X-LCF-Signature' => 'sha256='.hash_hmac('sha256', $ts.'.'.$json, $hook->secret)];
        foreach ($hook->headers ?? [] as $h) {
            $headers[(string) $h['name']] = (string) $h['value'];
        }
        $started = microtime(true);
        $update = ['attempt' => $attempt, 'updated_at' => now()->format('Y-m-d H:i:s.u'), 'request_headers' => json_encode($this->maskHeaders($headers, $hook)), 'error' => null];
        $at = null;
        try {
            $response = $this->egress->request($hook->method, $hook->url, ['headers' => $headers, 'body' => $hook->method === 'GET' ? '' : $json], $hook->timeout_seconds, 1_048_576);
            $status = $response->getStatusCode();
            $update += ['response_status' => $status, 'response_headers' => json_encode(array_map(static fn ($v) => implode(', ', $v), $response->getHeaders())),
                'response_body' => mb_strcut((string) $response->getBody(), 0, self::MAX_RESPONSE_LOG)];
            $update['status'] = $status < 300 ? 'succeeded' : ($status >= 500 || $status === 429 ? 'retrying' : 'failed');
            if ($update['status'] !== 'succeeded') {
                $update['error'] = __('platform.webhook_rejected', ['status' => $status]);
            }
        } catch (EgressViolation $e) {
            // Not allowed is final: retrying cannot make it allowed.
            $update += ['status' => 'blocked', 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            $update += ['status' => 'retrying', 'error' => mb_strcut($e->getMessage(), 0, 1000)];
        }
        $update['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        $update['next_attempt_at'] = null;
        if ($update['status'] === 'retrying') {
            if ($attempt > $hook->max_retries || ! $scheduleRetry) {
                $update['status'] = 'failed';
            } else {
                $at = now()->addMinutes(self::BACKOFF_MINUTES[min($attempt - 1, count(self::BACKOFF_MINUTES) - 1)]);
                $update['next_attempt_at'] = $at->format('Y-m-d H:i:s.u');
            }
        }
        DB::table('webhook_deliveries')->where('id', $deliveryId)->update($update);
        if ($at !== null) {
            DeliverWebhook::dispatch($deliveryId, $event, $json)->delay($at);
        }
    }

    /** @param  array<string, mixed>  $body */
    private function queue(Webhook $hook, string $event, array $body): int
    {
        $blocked = $body['__blocked'] ?? null;
        $id = $this->log($hook, $body, is_string($blocked) ? $blocked : null);
        if ($blocked === null) {
            DeliverWebhook::dispatch($id, $event, $this->json($body));
        }

        return $id;
    }

    /** The delivery row, with a masked copy of the request. @param  array<string, mixed>  $body */
    private function log(Webhook $hook, array $body, ?string $blocked): int
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return (int) DB::table('webhook_deliveries')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $hook->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'direction' => 'outgoing', 'webhook_id' => $hook->id, 'url' => $hook->url, 'method' => $hook->method,
            'request_body' => mb_strcut($this->maskBody($body), 0, self::MAX_REQUEST_LOG), 'attempt' => 0,
            'status' => $blocked === null ? 'pending' : 'blocked', 'error' => $blocked,
            'correlation_id' => app(CorrelationId::class)->get(),
        ]);
    }

    /** The payload as sent: internal markers removed. @param  array<string, mixed>  $body */
    private function json(array $body): string
    {
        unset($body['__blocked'], $body['__sensitive']);

        return (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function body(Webhook $hook, string $event, Form $form, array $payload): array
    {
        $body = ['event' => $event, 'occurred_at' => Carbon::now()->toIso8601String(), 'form' => ['uuid' => strtolower((string) $form->uuid), 'key' => $form->key]];
        $recordUuid = strtolower((string) ($payload['record'] ?? ''));
        if ($event === 'record.deleted') {
            return $body + ['record' => ['uuid' => $recordUuid]];
        }
        $owner = User::query()->withoutGlobalScopes()->find($hook->updated_by ?? $hook->created_by);
        $rt = $this->runtimes->forForm($form);
        $record = $rt === null ? null : $this->store->find($rt, $recordUuid);
        $sees = $owner !== null && $owner->status === 'active' && $this->access->allows($owner, "form.{$form->uuid}.view");
        if (! $sees || $rt === null || $record === null || ! $this->scope->allows($rt, $owner, 'view', (int) $record['id'])) {
            return $body + ['record' => ['uuid' => $recordUuid], '__blocked' => __('platform.webhook_owner_cannot_see')];
        }
        $levels = $this->fieldAccess->resolve($owner, $form->id, (string) $form->uuid, $rt->definition, 'view', $record['system']['status_id'] ?? null);
        $shown = $this->presenter->present($rt, $record, $levels['fields'], true);
        $body['record'] = ['uuid' => $shown['uuid'], 'row_version' => $shown['row_version'], 'title' => $shown['title'], 'values' => $shown['values'], 'status' => $shown['status'] ?? null];
        if ($event === 'record.status_changed') {
            $wf = WorkflowRuntime::for($rt);
            $body['transition'] = ['from' => $payload['from'] ?? null, 'to' => $payload['to'] ?? null, 'transition' => $wf->transitions[strtolower((string) ($payload['transition'] ?? ''))]['key'] ?? null];
        }
        $body['__sensitive'] = array_values(array_map(static fn ($f) => (string) $f['key'], array_filter($rt->mainFields(), static fn ($f) => ($f['flags']['sensitive'] ?? false) || ($f['flags']['encrypted'] ?? false))));

        return $body;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function maskHeaders(array $headers, Webhook $hook): array
    {
        $custom = array_map(static fn ($h) => strtolower((string) $h['name']), $hook->headers ?? []);
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-lcf-signature' || in_array(strtolower($name), $custom, true)) {
                $headers[$name] = '***';
            }
        }

        return $headers;
    }

    /** The logged copy: sensitive values replaced. @param  array<string, mixed>  $body */
    private function maskBody(array $body): string
    {
        foreach ($body['__sensitive'] ?? [] as $key) {
            if (isset($body['record']['values']) && array_key_exists($key, $body['record']['values'])) {
                $body['record']['values'][$key] = '***';
            }
        }

        return $this->json($body);
    }
}
