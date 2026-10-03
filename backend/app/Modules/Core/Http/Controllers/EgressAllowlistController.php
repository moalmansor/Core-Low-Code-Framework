<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Hosts outbound requests may reach (specification §4.15, §5). */
final class EgressAllowlistController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_settings');

        return response()->json(['data' => EgressAllowlistEntry::query()->orderBy('host_pattern')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_settings');
        $entry = EgressAllowlistEntry::query()->create($this->validated($request, null));
        app(AuditWriter::class)->record('config.egress_added', 'config', objectType: 'egress_allowlist', objectId: $entry->id, meta: ['host' => $entry->host_pattern]);

        return response()->json(['data' => $entry], 201);
    }

    public function update(Request $request, EgressAllowlistEntry $entry): JsonResponse
    {
        Gate::authorize('system.manage_settings');
        $entry->fill($this->validated($request, $entry))->save();
        app(AuditWriter::class)->record('config.egress_changed', 'config', objectType: 'egress_allowlist', objectId: $entry->id, meta: ['host' => $entry->host_pattern]);

        return response()->json(['data' => $entry]);
    }

    public function destroy(EgressAllowlistEntry $entry): JsonResponse
    {
        Gate::authorize('system.manage_settings');
        $entry->delete();
        app(AuditWriter::class)->record('config.egress_removed', 'config', objectType: 'egress_allowlist', objectId: $entry->id, meta: ['host' => $entry->host_pattern]);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?EgressAllowlistEntry $entry): array
    {
        $data = $request->validate([
            'host_pattern' => [$entry === null ? 'required' : 'sometimes', 'string', 'max:253',
                // hostname or *.suffix; IP literals are not accepted
                'regex:/^(\*\.)?([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
                Rule::unique(EgressAllowlistEntry::class, 'host_pattern')->where('organization_id', app(TenantContext::class)->organizationId())->ignore($entry?->id)],
            'ports' => [$entry === null ? 'required' : 'sometimes', 'array', 'min:1', 'max:10'],
            'ports.*' => ['integer', 'between:1,65535'],
            'allow_http' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['host_pattern'])) {
            $data['host_pattern'] = strtolower($data['host_pattern']);
        }

        return $data;
    }
}
