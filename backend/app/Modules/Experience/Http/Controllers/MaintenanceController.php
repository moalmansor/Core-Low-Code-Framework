<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Experience\Maintenance;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** System-wide maintenance mode (specification §4.29, ADR-0054). */
final class MaintenanceController extends Controller
{
    public function __construct(private readonly Maintenance $maintenance) {}

    /** Whether the system is offline, and whether it is offline for the person asking. */
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $system = $this->maintenance->system();

        return response()->json(['data' => [
            'active' => $system['active'], 'until' => $system['until'], 'message' => $system['message'],
            'messages' => $this->maintenance->exempt($user) ? (object) $system['messages'] : null,
            'blocked' => $this->maintenance->blocksSystem($user), 'can_manage' => $this->maintenance->exempt($user),
        ]]);
    }

    public function update(Request $request, SettingsService $settings, AuditWriter $audit): JsonResponse
    {
        Gate::authorize('system.enable_maintenance_mode');
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'until' => ['sometimes', 'nullable', 'date', 'after:now'],
            'message' => ['sometimes', 'array'],
            'message.*' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($data, $settings, $audit): void {
            $settings->update('maintenance', [
                'enabled' => $data['enabled'],
                'until' => isset($data['until']) ? Carbon::parse($data['until'])->utc()->format('Y-m-d H:i:s') : null,
            ]);
            if (isset($data['message'])) {
                $platform = Organization::query()->findOrFail(app(TenantContext::class)->platformOrganizationId());
                $platform->setTranslations('maintenance_message', array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', $data['message']), static fn ($v) => $v !== ''));
            }
            $audit->record($data['enabled'] ? 'maintenance.enabled' : 'maintenance.disabled', 'config', null, 'system', null, ['until' => $data['until'] ?? null], (int) Auth::id());
        });

        return $this->show();
    }
}
