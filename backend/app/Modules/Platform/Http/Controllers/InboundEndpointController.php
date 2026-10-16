<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\InboundEndpoint;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inbound endpoints and their call log (specification §4.33, ADR-0050), for
 * holders of "Manage integrations". A webhook endpoint's signing secret is
 * shown once, when it is made or replaced. Choosing a run-as user other than
 * oneself needs "Manage permissions", as for automations.
 */
final class InboundEndpointController extends Controller
{
    public function __construct(private readonly AuditWriter $audit, private readonly FormRuntimes $runtimes, private readonly AccessResolver $access) {}

    public function index(Request $request): JsonResponse
    {
        // Automation builders pick an endpoint of their form for the inbound webhook trigger.
        abort_unless(Gate::any(['system.manage_integrations', 'system.manage_automations']), 403);
        $form = $request->query('form');
        $q = InboundEndpoint::query()->orderBy('slug');
        if (is_string($form) && Str::isUuid($form)) {
            $q->where('form_id', (int) Form::query()->where('uuid', strtolower($form))->value('id'));
        }
        $rows = $q->get();
        $stats = DB::table('webhook_deliveries')->whereIn('inbound_endpoint_id', $rows->pluck('id'))->where('created_at', '>=', now()->subDay()->format('Y-m-d H:i:s.u'))
            ->selectRaw('inbound_endpoint_id, status, count(*) as n')->groupBy('inbound_endpoint_id', 'status')->get()->groupBy('inbound_endpoint_id');

        return response()->json(['data' => $rows->map(fn (InboundEndpoint $e) => $this->present($e) + [
            'last_day' => collect($stats[$e->id] ?? [])->mapWithKeys(static fn ($r) => [$r->status => (int) $r->n])->all() ?: (object) [],
        ])->all()]);
    }

    /** The fields of a published form a call can set, and which of them can find a record. */
    public function fields(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $data = $request->validate(['form' => ['required', 'uuid']]);
        $form = Form::query()->where('uuid', strtolower($data['form']))->first();
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['form' => __('platform.inbound_form_unpublished')]);
        }
        $tr = app(Translator::class);
        $out = [];
        foreach ($rt->mainFields() as $f) {
            $type = $rt->type($f);
            if ($type === null || ! $rt->isStored($f)) {
                continue;
            }
            $out[] = ['uuid' => $f['uuid'], 'key' => $f['key'], 'label' => $tr->labelOf($f['i18n']['label'] ?? null, (string) $f['key']), 'type' => $f['type'],
                'matchable' => in_array($type->valueType, ['text', 'number'], true) && ! $type->multiple && $rt->column($f['uuid']) !== null, 'rows' => false];
        }
        foreach ($rt->repeaterKeys as $key => $groupUuid) {
            $out[] = ['uuid' => $groupUuid, 'key' => $key, 'label' => $tr->labelOf($rt->groups[$groupUuid]['i18n']['title'] ?? null, (string) $key), 'type' => 'repeater', 'matchable' => false, 'rows' => true];
        }

        return response()->json(['data' => $out]);
    }

    public function show(string $endpoint): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => $this->present($this->find($endpoint))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $data = $this->validated($request, null);
        $endpoint = new InboundEndpoint;
        $secret = $data['kind'] === 'webhook' ? 'whsec_'.Str::random(40) : null;
        $this->fill($endpoint, $data);
        $endpoint->signature_secret = $secret;
        $endpoint->save();
        $this->audit->record('inbound_endpoint.created', 'config', null, 'inbound_endpoint', $endpoint->id, ['slug' => $endpoint->slug, 'kind' => $endpoint->kind], null, null, $endpoint->form_id);

        return response()->json(['data' => $this->present($endpoint) + ($secret === null ? [] : ['secret' => $secret])], 201);
    }

    public function update(Request $request, string $endpoint): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $e = $this->find($endpoint);
        $data = $this->validated($request, $e);
        $before = $this->present($e);
        $this->fill($e, $data);
        $secret = null;
        // Becoming a webhook endpoint needs a secret; becoming an API endpoint drops it.
        if ($e->kind === 'webhook' && $e->signature_secret === null) {
            $secret = 'whsec_'.Str::random(40);
            $e->signature_secret = $secret;
        } elseif ($e->kind === 'api') {
            $e->signature_secret = null;
        }
        $e->save();
        $after = $this->present($e);
        $changes = [];
        foreach (['slug', 'kind', 'operation', 'form', 'match_field', 'mapping', 'signature_header', 'signature_algorithm', 'run_as_user', 'is_active'] as $k) {
            if ($before[$k] != $after[$k]) {
                $changes[] = ['field_key' => $k, 'old' => $before[$k], 'new' => $after[$k]];
            }
        }
        $this->audit->record('inbound_endpoint.updated', 'config', $changes, 'inbound_endpoint', $e->id, ['slug' => $e->slug], null, null, $e->form_id);

        return response()->json(['data' => $after + ($secret === null ? [] : ['secret' => $secret])]);
    }

    public function destroy(string $endpoint): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $e = $this->find($endpoint);
        if (DB::table('automation_triggers')->where('inbound_endpoint_id', $e->id)->exists()) {
            throw ValidationException::withMessages(['endpoint' => __('platform.inbound_in_use')]);
        }
        DB::transaction(function () use ($e): void {
            // The call log stays; its rows keep the address that was called.
            DB::table('webhook_deliveries')->where('inbound_endpoint_id', $e->id)->update(['inbound_endpoint_id' => null]);
            $e->delete();
        });
        $this->audit->record('inbound_endpoint.deleted', 'config', null, 'inbound_endpoint', $e->id, ['slug' => $e->slug], null, null, $e->form_id);

        return response()->json(null, 204);
    }

    public function rotateSecret(string $endpoint): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $e = $this->find($endpoint);
        if ($e->kind !== 'webhook') {
            throw ValidationException::withMessages(['kind' => __('platform.inbound_no_secret')]);
        }
        $secret = 'whsec_'.Str::random(40);
        $e->signature_secret = $secret;
        $e->save();
        $this->audit->record('inbound_endpoint.secret_rotated', 'security', null, 'inbound_endpoint', $e->id, ['slug' => $e->slug], null, null, $e->form_id);

        return response()->json(['data' => ['secret' => $secret]]);
    }

    public function deliveries(Request $request, string $endpoint): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $e = $this->find($endpoint);
        $data = $request->validate(['status' => ['sometimes', 'nullable', Rule::in(['succeeded', 'failed', 'signature_invalid'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = DB::table('webhook_deliveries')->where('inbound_endpoint_id', $e->id)->when($data['status'] ?? null, static fn ($q, $s) => $q->where('status', $s));
        $total = (clone $q)->count();
        $page = (int) ($data['page'] ?? 1);
        $rows = $q->orderByDesc('id')->forPage($page, 25)->get();

        return response()->json(['data' => $rows->map(static function ($d) {
            $answer = json_decode((string) $d->response_body, true);

            return [
                'uuid' => strtolower((string) $d->uuid), 'created_at' => Carbon::parse((string) $d->created_at, 'UTC')->toIso8601String(),
                'status' => $d->status, 'response_status' => $d->response_status === null ? null : (int) $d->response_status,
                'record' => is_array($answer) ? ($answer['data']['record'] ?? null) : null, 'operation' => is_array($answer) ? ($answer['data']['operation'] ?? null) : null,
                'error' => $d->error, 'ip' => $d->resolved_ip,
            ];
        })->all(), 'meta' => ['total' => $total, 'page' => $page, 'per_page' => 25]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?InboundEndpoint $current): array
    {
        $org = app(TenantContext::class)->organizationId();
        $data = $request->validate([
            'form' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['api', 'webhook'])],
            // Unique across the installation: the public address carries no organization.
            'slug' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{1,63}$/', Rule::unique('inbound_endpoints', 'slug')->ignore($current?->id)],
            'operation' => ['required', Rule::in(['create', 'update', 'upsert'])],
            'match_field' => ['nullable', 'uuid', Rule::requiredIf(fn () => in_array($request->input('operation'), ['update', 'upsert'], true))],
            'mapping' => ['required', 'array', 'min:1', 'max:200'],
            'mapping.*' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/'],
            'signature_header' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/', Rule::requiredIf(fn () => $request->input('kind') === 'webhook')],
            'signature_algorithm' => ['nullable', Rule::in(['hmac_sha256', 'hmac_sha512']), Rule::requiredIf(fn () => $request->input('kind') === 'webhook')],
            'run_as_user' => ['nullable', 'uuid', Rule::requiredIf(fn () => $request->input('kind') === 'webhook')],
            'is_active' => ['required', 'boolean'],
        ]);
        $form = Form::query()->where('uuid', strtolower($data['form']))->first();
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($form === null || $rt === null) {
            throw ValidationException::withMessages(['form' => __('platform.inbound_form_unpublished')]);
        }
        $data['form_id'] = $form->id;
        $data['mapping'] = $this->mapping($rt, $data['mapping']);
        $data['match_field_id'] = null;
        if (in_array($data['operation'], ['update', 'upsert'], true)) {
            $field = $rt->fields[strtolower((string) $data['match_field'])] ?? null;
            $type = $field === null ? null : $rt->type($field);
            if ($field === null || $type === null || ! in_array($type->valueType, ['text', 'number'], true) || $type->multiple || $rt->column($field['uuid']) === null) {
                throw ValidationException::withMessages(['match_field' => __('platform.inbound_bad_match_field')]);
            }
            if (! array_key_exists((string) $field['key'], $data['mapping'])) {
                throw ValidationException::withMessages(['match_field' => __('platform.inbound_match_unmapped')]);
            }
            $data['match_field_id'] = (int) DB::table('fields')->where('uuid', $field['uuid'])->value('id');
        }
        /** @var User $actor */
        $actor = Auth::user();
        if ($data['kind'] === 'webhook') {
            $runAs = User::query()->where('uuid', strtolower((string) $data['run_as_user']))->where('status', 'active')->first();
            if ($runAs === null || (int) $runAs->organization_id !== $org) {
                throw ValidationException::withMessages(['run_as_user' => __('platform.inbound_bad_run_as')]);
            }
            $changing = $current === null || $current->run_as_user_id !== $runAs->id || $current->kind !== 'webhook';
            if ($changing && $runAs->id !== $actor->id && ! $this->access->allows($actor, 'system.manage_permissions')) {
                throw ValidationException::withMessages(['run_as_user' => __('automations.run_as_other_needs_permissions')]);
            }
            if ($current !== null && $current->kind === 'webhook' && $current->run_as_user_id !== $actor->id && ! $this->access->allows($actor, 'system.manage_permissions')) {
                throw ValidationException::withMessages(['run_as_user' => __('automations.run_as_other_needs_permissions')]);
            }
            $data['run_as_user_id'] = $runAs->id;
        } else {
            // An API endpoint acts as the owner of the token that calls it; the column keeps whoever set it up.
            $data['run_as_user_id'] = $current->run_as_user_id ?? $actor->id;
            $data['signature_header'] = null;
            $data['signature_algorithm'] = null;
        }

        return $data;
    }

    /**
     * Each key a field of the form that a call can set: a stored field of the
     * record or a repeater (whose rows come as a list).
     *
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    private function mapping(FormRuntime $rt, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $key => $path) {
            $key = (string) $key;
            $uuid = $rt->keys[$key] ?? null;
            $field = $uuid === null ? null : ($rt->fields[$uuid] ?? null);
            $settable = ($field !== null && $rt->isStored($field)) || isset($rt->repeaterKeys[$key]);
            if (! $settable) {
                throw ValidationException::withMessages(["mapping.{$key}" => __('platform.inbound_bad_mapping_field')]);
            }
            $out[$key] = (string) $path;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $data */
    private function fill(InboundEndpoint $e, array $data): void
    {
        $e->forceFill([
            'form_id' => $data['form_id'], 'kind' => $data['kind'], 'slug' => $data['slug'], 'operation' => $data['operation'],
            'match_field_id' => $data['match_field_id'], 'payload_mapping' => $data['mapping'],
            'signature_header' => $data['signature_header'] ?? null, 'signature_algorithm' => $data['signature_algorithm'] ?? null,
            'run_as_user_id' => $data['run_as_user_id'], 'is_active' => $data['is_active'],
        ]);
    }

    private function find(string $uuid): InboundEndpoint
    {
        return InboundEndpoint::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(InboundEndpoint $e): array
    {
        $form = Form::query()->find($e->form_id);
        $runAs = User::query()->withoutGlobalScopes()->find($e->run_as_user_id);

        return [
            'uuid' => $e->uuid, 'slug' => $e->slug, 'kind' => $e->kind, 'operation' => $e->operation,
            'url' => url('/api/inbound/'.$e->slug),
            'form' => $form?->uuid, 'form_name' => $form === null ? null : ($form->translate('name') ?? Translator::humanize((string) $form->key)),
            'match_field' => $e->match_field_id === null ? null : strtolower((string) DB::table('fields')->where('id', $e->match_field_id)->value('uuid')),
            'mapping' => (object) $e->payload_mapping,
            'signature_header' => $e->signature_header, 'signature_algorithm' => $e->signature_algorithm,
            'run_as_user' => $e->kind === 'webhook' ? $runAs?->uuid : null, 'run_as_user_name' => $e->kind === 'webhook' ? $runAs?->name : null,
            'is_active' => $e->is_active,
        ];
    }
}
