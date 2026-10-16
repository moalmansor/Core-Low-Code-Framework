<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\EgressViolation;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Platform\Models\Webhook;
use App\Modules\Platform\Webhooks;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Outgoing webhooks and their delivery log (specification §4.22), for
 * holders of "Manage integrations". The signing secret is shown once, when
 * the webhook is made or its secret is replaced; custom header values are
 * write-only.
 */
final class WebhookController extends Controller
{
    /** Headers the framework sets itself; a webhook cannot replace them. */
    private const RESERVED = ['host', 'content-length', 'content-type', 'user-agent', 'x-lcf-event', 'x-lcf-delivery', 'x-lcf-timestamp', 'x-lcf-signature'];

    public function __construct(private readonly Webhooks $webhooks, private readonly AuditWriter $audit, private readonly EgressGateway $egress) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $hooks = Webhook::query()->orderBy('key')->get();
        $stats = DB::table('webhook_deliveries')->whereIn('webhook_id', $hooks->pluck('id'))->where('created_at', '>=', now()->subDay()->format('Y-m-d H:i:s.u'))
            ->selectRaw('webhook_id, status, count(*) as n')->groupBy('webhook_id', 'status')->get()->groupBy('webhook_id');

        return response()->json(['data' => $hooks->map(fn (Webhook $h) => $this->present($h) + ['last_day' => collect($stats[$h->id] ?? [])->mapWithKeys(static fn ($r) => [$r->status => (int) $r->n])->all() ?: (object) []])->all()]);
    }

    public function show(string $webhook): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => $this->present($this->find($webhook))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $data = $this->validated($request, null);
        $hook = new Webhook;
        $secret = 'whsec_'.Str::random(40);
        DB::transaction(function () use ($hook, $data, $secret): void {
            $this->fill($hook, $data);
            $hook->secret = $secret;
            $hook->save();
            $hook->setTranslations('name', $data['name']);
        });
        $this->audit->record('webhook.created', 'config', null, 'webhook', $hook->id, ['key' => $hook->key, 'url_host' => parse_url($hook->url, PHP_URL_HOST)]);

        return response()->json(['data' => $this->present($hook) + ['secret' => $secret]], 201);
    }

    public function update(Request $request, string $webhook): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $hook = $this->find($webhook);
        $data = $this->validated($request, $hook);
        $before = $this->present($hook);
        DB::transaction(function () use ($hook, $data): void {
            $this->fill($hook, $data);
            $hook->save();
            $hook->setTranslations('name', $data['name']);
        });
        $after = $this->present($hook);
        $changes = [];
        foreach (['key', 'url', 'method', 'events', 'form', 'timeout_seconds', 'max_retries', 'is_active', 'header_names'] as $k) {
            if ($before[$k] !== $after[$k]) {
                $changes[] = ['field_key' => $k, 'old' => $before[$k], 'new' => $after[$k]];
            }
        }
        $this->audit->record('webhook.updated', 'config', $changes, 'webhook', $hook->id, ['key' => $hook->key]);

        return response()->json(['data' => $after]);
    }

    public function destroy(string $webhook): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $hook = $this->find($webhook);
        // The delivery log stays: its rows keep the address they were sent to.
        DB::transaction(function () use ($hook): void {
            DB::table('webhook_deliveries')->where('webhook_id', $hook->id)->update(['webhook_id' => null]);
            $hook->delete();
        });
        $this->audit->record('webhook.deleted', 'config', null, 'webhook', $hook->id, ['key' => $hook->key]);

        return response()->json(null, 204);
    }

    public function rotateSecret(string $webhook): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $hook = $this->find($webhook);
        $secret = 'whsec_'.Str::random(40);
        $hook->secret = $secret;
        $hook->save();
        $this->audit->record('webhook.secret_rotated', 'security', null, 'webhook', $hook->id, ['key' => $hook->key]);

        return response()->json(['data' => ['secret' => $secret]]);
    }

    public function test(string $webhook): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $id = $this->webhooks->test($this->find($webhook));

        return response()->json(['data' => $this->delivery((string) DB::table('webhook_deliveries')->where('id', $id)->value('uuid'))->getData(true)['data']]);
    }

    public function deliveries(Request $request, string $webhook): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $hook = $this->find($webhook);
        $data = $request->validate(['status' => ['sometimes', 'nullable', Rule::in(['pending', 'succeeded', 'failed', 'retrying', 'blocked'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = DB::table('webhook_deliveries')->where('webhook_id', $hook->id)->when($data['status'] ?? null, static fn ($q, $s) => $q->where('status', $s));
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage((int) ($data['page'] ?? 1), 25)->get();

        return response()->json(['data' => $rows->map(fn ($d) => $this->summary($d))->all(), 'meta' => ['total' => $total, 'page' => (int) ($data['page'] ?? 1), 'per_page' => 25]]);
    }

    public function delivery(string $delivery): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $d = DB::table('webhook_deliveries')->where('organization_id', app(TenantContext::class)->organizationId())->where('uuid', strtolower($delivery))->first();
        abort_if($d === null, 404);

        return response()->json(['data' => $this->summary($d) + [
            'request_headers' => json_decode((string) $d->request_headers, true) ?: (object) [], 'request_body' => $d->request_body,
            'response_headers' => json_decode((string) $d->response_headers, true) ?: (object) [], 'response_body' => $d->response_body,
        ]]);
    }

    public function retry(string $delivery): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $d = DB::table('webhook_deliveries')->where('organization_id', app(TenantContext::class)->organizationId())->where('uuid', strtolower($delivery))->where('direction', 'outgoing')->first();
        abort_if($d === null, 404);
        $id = $this->webhooks->resend((int) $d->id);
        $this->audit->record('webhook.resent', 'operations', null, 'webhook', (int) $d->webhook_id, ['delivery' => strtolower((string) $d->uuid)]);

        return response()->json(['data' => $this->summary(DB::table('webhook_deliveries')->where('id', $id)->first())], 202);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Webhook $current): array
    {
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,47}$/', Rule::unique('webhooks', 'key')->where('organization_id', app(TenantContext::class)->organizationId())->ignore($current?->id)],
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'form' => ['sometimes', 'nullable', 'uuid'],
            'url' => ['required', 'string', 'max:2048', 'url'],
            'method' => ['required', Rule::in(['POST', 'PUT', 'PATCH', 'DELETE', 'GET'])],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['distinct', Rule::in(Webhooks::EVENTS)],
            'headers' => ['sometimes', 'nullable', 'array', 'max:20'],
            'headers.*.name' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/'],
            'headers.*.value' => ['nullable', 'string', 'max:1000'],
            'timeout_seconds' => ['required', 'integer', 'between:1,30'],
            'max_retries' => ['required', 'integer', 'between:0,5'],
            'is_active' => ['required', 'boolean'],
        ], [], ['name.'.$default => __('platform.name')]);
        try {
            $this->egress->permits(new Uri($data['url']));
        } catch (EgressViolation $e) {
            throw ValidationException::withMessages(['url' => __('platform.url_not_allowed', ['reason' => $e->getMessage()])]);
        }
        $headers = [];
        $old = collect($current->headers ?? [])->keyBy(static fn ($h) => strtolower((string) $h['name']));
        foreach ($data['headers'] ?? [] as $i => $h) {
            if (in_array(strtolower($h['name']), self::RESERVED, true)) {
                throw ValidationException::withMessages(["headers.{$i}.name" => __('platform.header_reserved')]);
            }
            // A header value left empty keeps the value already saved (values are never sent back).
            $value = $h['value'] ?? null;
            if (($value === null || $value === '') && $old->has(strtolower($h['name']))) {
                $value = (string) $old[strtolower($h['name'])]['value'];
            }
            $headers[] = ['name' => $h['name'], 'value' => (string) $value];
        }
        $data['headers'] = $headers;
        $data['form_id'] = null;
        if (($data['form'] ?? null) !== null) {
            $data['form_id'] = Form::query()->where('uuid', strtolower($data['form']))->value('id');
            if ($data['form_id'] === null) {
                throw ValidationException::withMessages(['form' => __('platform.unknown_form')]);
            }
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Webhook $hook, array $data): void
    {
        $hook->forceFill([
            'key' => $data['key'], 'form_id' => $data['form_id'], 'url' => $data['url'], 'method' => $data['method'],
            'events' => array_values($data['events']), 'headers' => $data['headers'], 'timeout_seconds' => $data['timeout_seconds'],
            'max_retries' => $data['max_retries'], 'is_active' => $data['is_active'],
        ]);
    }

    private function find(string $uuid): Webhook
    {
        return Webhook::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(Webhook $h): array
    {
        $form = $h->form_id === null ? null : Form::query()->find($h->form_id);

        return [
            'uuid' => $h->uuid, 'key' => $h->key, 'name' => (object) $h->translationsFor('name'), 'label' => app(Translator::class)->labelOf($h->translationsFor('name'), $h->key),
            'form' => $form?->uuid, 'form_name' => $form === null ? null : ($form->translate('name') ?? Translator::humanize((string) $form->key)),
            'url' => $h->url, 'method' => $h->method, 'events' => $h->events,
            'header_names' => array_values(array_map(static fn ($x) => (string) $x['name'], $h->headers ?? [])),
            'timeout_seconds' => $h->timeout_seconds, 'max_retries' => $h->max_retries, 'is_active' => $h->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(object $d): array
    {
        $body = json_decode((string) $d->request_body, true);

        return [
            'uuid' => strtolower((string) $d->uuid), 'created_at' => Carbon::parse((string) $d->created_at, 'UTC')->toIso8601String(),
            'event' => is_array($body) ? ($body['event'] ?? null) : null, 'record' => is_array($body) ? ($body['record']['uuid'] ?? null) : null,
            'status' => $d->status, 'attempt' => (int) $d->attempt, 'response_status' => $d->response_status === null ? null : (int) $d->response_status,
            'duration_ms' => $d->duration_ms === null ? null : (int) $d->duration_ms, 'error' => $d->error,
            'next_attempt_at' => $d->next_attempt_at === null ? null : Carbon::parse((string) $d->next_attempt_at, 'UTC')->toIso8601String(),
        ];
    }
}
