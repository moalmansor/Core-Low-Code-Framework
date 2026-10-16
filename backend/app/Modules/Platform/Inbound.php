<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Automation\AutomationEngine;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\InboundEndpoint;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Receives calls on inbound endpoints (specification §4.33, ADR-0050).
 *
 * - The endpoint is found by its slug, which is unique across the
 *   installation because the address carries no organization.
 * - A `webhook` endpoint checks `HMAC(secret, raw body)` in its signature
 *   header (`sha256=<hex>` or bare hex) and acts as its run-as user. An `api`
 *   endpoint takes a personal API token with write access to the form and
 *   acts as the token's owner. Either way the record goes through the same
 *   pipeline as the UI: permissions, field access, rules, validation,
 *   workflow, audit and the submission journal (source `inbound_webhook`).
 * - The mapping takes each field's value from a dot path in the JSON body;
 *   a path the body does not carry leaves the field out. Update and upsert
 *   find the record by the match field; the update is made against the
 *   version read on arrival, so a change saved meanwhile is a conflict, never
 *   overwritten.
 * - Every call is logged as an incoming delivery, with the signature and
 *   token masked and sensitive fields hidden, and starts the automations
 *   listening to the endpoint.
 */
final class Inbound
{
    public const MAX_BYTES = 1_048_576;

    private const MAX_LOG = 65_536;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordPipeline $pipeline,
        private readonly RecordStore $store,
        private readonly ApiTokens $tokens,
        private readonly TenantContext $tenant,
        private readonly AutomationEngine $automations,
    ) {}

    public function receive(Request $request, string $slug): JsonResponse
    {
        /** @var InboundEndpoint|null $endpoint */
        $endpoint = InboundEndpoint::query()->withoutGlobalScope('organization')->where('slug', strtolower($slug))->first();
        if ($endpoint === null || ! $endpoint->is_active) {
            return response()->json(['message' => __('platform.inbound_unknown')], 404);
        }
        $this->tenant->set($endpoint->organization_id);
        $raw = (string) $request->getContent();
        if (strlen($raw) > self::MAX_BYTES) {
            return $this->answer($endpoint, $request, $raw, 'failed', 413, ['message' => __('platform.inbound_too_large')]);
        }

        // Who is calling.
        if ($endpoint->kind === 'webhook') {
            if (! $this->signed($endpoint, $request, $raw)) {
                return $this->answer($endpoint, $request, $raw, 'signature_invalid', 401, ['message' => __('platform.inbound_bad_signature')]);
            }
            $user = User::query()->whereKey($endpoint->run_as_user_id)->where('status', 'active')->first();
        } else {
            $user = $this->tokens->authenticate($request);
            $token = ApiTokens::current($request);
            $this->tenant->set($endpoint->organization_id);
            if ($user === null || $token === null || (int) $user->organization_id !== $endpoint->organization_id) {
                return $this->answer($endpoint, $request, $raw, 'failed', 401, ['message' => __('platform.inbound_bad_token')]);
            }
            $form = Form::query()->find($endpoint->form_id);
            if ($form === null || ! $token->allowsForm(strtolower((string) $form->uuid), true)) {
                return $this->answer($endpoint, $request, $raw, 'failed', 403, ['message' => __('platform.token_read_only')]);
            }
        }
        $form ??= Form::query()->find($endpoint->form_id);
        $rt = $form === null || ! in_array($form->state, ['published', 'schema_inconsistent'], true) ? null : $this->runtimes->forForm($form);
        if ($user === null || $rt === null) {
            return $this->answer($endpoint, $request, $raw, 'failed', 503, ['message' => __('platform.inbound_unavailable')]);
        }
        $body = json_decode($raw, true);
        if (! is_array($body) || array_is_list($body) && $body !== []) {
            return $this->answer($endpoint, $request, $raw, 'failed', 422, ['message' => __('platform.inbound_not_json')], $rt);
        }

        $values = $this->map($endpoint, $body);
        $key = (string) $request->header('Idempotency-Key', '');
        $key = preg_match('/^[A-Za-z0-9-]{16,64}$/', $key) === 1 ? 'in-'.$endpoint->id.'-'.$key : (string) Str::uuid7();
        $previous = Auth::user();
        Auth::setUser($user);
        try {
            $current = null;
            if ($endpoint->operation !== 'create') {
                $current = $this->match($endpoint, $rt, $values);
                if ($current === null && $endpoint->operation === 'update') {
                    return $this->answer($endpoint, $request, $raw, 'failed', 404, ['message' => __('platform.inbound_no_match')], $rt);
                }
            }
            if ($current === null) {
                $done = $this->pipeline->create($rt, $user, $values, [], $key, 'inbound_webhook');
                $result = ['record' => $done['uuid'], 'operation' => 'created'];
                $status = 201;
            } else {
                $done = $this->pipeline->update($rt, $user, $current['uuid'], (int) $current['row_version'], $values, $key, 'inbound_webhook');
                $result = ['record' => $done['uuid'], 'operation' => 'updated'];
                $status = 200;
            }
        } catch (RecordException $e) {
            return $this->answer($endpoint, $request, $raw, 'failed', $e->status, ['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $rt);
        } catch (ValidationException $e) {
            return $this->answer($endpoint, $request, $raw, 'failed', 422, ['message' => $e->getMessage(), 'errors' => $e->errors()], $rt);
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
        $response = $this->answer($endpoint, $request, $raw, 'succeeded', $status, ['data' => $result], $rt);
        try {
            $this->automations->inboundArrived($endpoint->id, $done['uuid'], $done['id'], (string) $response->headers->get('X-LCF-Delivery'));
        } catch (Throwable $e) {
            // The record is saved; a failing automation is reported in its own run log.
            report($e);
        }

        return $response;
    }

    /**
     * The values the mapping takes from the body, by field key.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function map(InboundEndpoint $endpoint, array $body): array
    {
        $values = [];
        foreach ($endpoint->payload_mapping as $fieldKey => $path) {
            $missing = new \stdClass;
            $value = data_get($body, (string) $path, $missing);
            if ($value !== $missing) {
                $values[(string) $fieldKey] = $value;
            }
        }

        return $values;
    }

    /** The signature check: constant-time, over the raw body. */
    private function signed(InboundEndpoint $endpoint, Request $request, string $raw): bool
    {
        $given = trim((string) $request->header((string) $endpoint->signature_header, ''));
        if ($given === '' || $endpoint->signature_secret === null) {
            return false;
        }
        $algo = $endpoint->signature_algorithm === 'hmac_sha512' ? 'sha512' : 'sha256';
        $given = str_starts_with(strtolower($given), $algo.'=') ? substr($given, strlen($algo) + 1) : $given;

        return hash_equals(hash_hmac($algo, $raw, $endpoint->signature_secret), strtolower($given));
    }

    /**
     * The record whose match field holds the mapped value, if any.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function match(InboundEndpoint $endpoint, FormRuntime $rt, array $values): ?array
    {
        $uuid = $endpoint->match_field_id === null ? null : strtolower((string) DB::table('fields')->where('id', $endpoint->match_field_id)->value('uuid'));
        $field = $uuid === null ? null : ($rt->fields[$uuid] ?? null);
        $column = $uuid === null ? null : ($rt->column($uuid)['name'] ?? null);
        if ($field === null || $column === null) {
            throw new RecordException(422, 'inbound_match', __('platform.inbound_match_missing'));
        }
        $value = $values[(string) $field['key']] ?? null;
        if (! is_scalar($value) || $value === '') {
            throw new RecordException(422, 'inbound_match', __('platform.inbound_match_value'));
        }
        $ids = DB::table($rt->table)->where($column, $value)->whereNull('deleted_at')->limit(2)->pluck('id');
        if ($ids->count() > 1) {
            throw new RecordException(409, 'inbound_ambiguous', __('platform.inbound_ambiguous'));
        }

        return $ids->isEmpty() ? null : $this->store->findById($rt, (int) $ids->first());
    }

    /**
     * Log the call as an incoming delivery and answer it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function answer(InboundEndpoint $endpoint, Request $request, string $raw, string $status, int $code, array $payload, ?FormRuntime $rt = null): JsonResponse
    {
        $now = now()->format('Y-m-d H:i:s.u');
        $uuid = (string) Str::uuid7();
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $secret = in_array($name, ['authorization', 'cookie', strtolower((string) $endpoint->signature_header)], true);
            $headers[$name] = $secret ? '***' : mb_substr(implode(', ', $values), 0, 500);
        }
        $responseBody = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('webhook_deliveries')->insert([
            'uuid' => $uuid, 'organization_id' => $endpoint->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'direction' => 'incoming', 'inbound_endpoint_id' => $endpoint->id, 'url' => mb_substr('/'.$request->path(), 0, 2048), 'method' => 'POST',
            'resolved_ip' => $request->ip(), 'request_headers' => json_encode($headers), 'request_body' => mb_strcut($this->masked($endpoint, $raw, $rt), 0, self::MAX_LOG),
            'response_status' => $code, 'response_body' => mb_strcut($responseBody, 0, 16_384), 'attempt' => 1, 'status' => $status,
            'error' => $status === 'succeeded' ? null : mb_substr((string) ($payload['message'] ?? ''), 0, 1000),
            'correlation_id' => app(CorrelationId::class)->get(),
        ]);

        return response()->json($payload, $code)->header('X-LCF-Delivery', $uuid);
    }

    /** The logged copy of the body: values of sensitive fields hidden at their mapped paths. */
    private function masked(InboundEndpoint $endpoint, string $raw, ?FormRuntime $rt): string
    {
        $body = json_decode($raw, true);
        if (! is_array($body) || $rt === null) {
            return $raw;
        }
        foreach ($endpoint->payload_mapping as $fieldKey => $path) {
            $field = isset($rt->keys[$fieldKey]) ? ($rt->fields[$rt->keys[$fieldKey]] ?? null) : null;
            if ($field !== null && (($field['flags']['sensitive'] ?? false) || ($field['flags']['encrypted'] ?? false)) && data_get($body, (string) $path) !== null) {
                data_set($body, (string) $path, '***');
            }
        }

        return (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
