<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Applications (specification §4.27): create, edit, archive, retire, maintenance. */
final class ApplicationController extends Controller
{
    public function __construct(private readonly Translator $translator, private readonly ObjectPermissions $permissions) {}

    public function index(AccessResolver $access): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $manage = $access->allows($user, 'system.manage_applications') || $access->allows($user, 'system.manage_forms');
        $apps = Application::query()->orderBy('sort_order')->orderBy('key')->get()
            ->filter(static fn (Application $a) => $manage || ($a->status === 'active' && $access->allows($user, "app.{$a->uuid}.access")));
        $names = $this->translator->many('application', $apps->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name', 'description']);
        $counts = DB::table('forms')->whereNull('deleted_at')->selectRaw('application_id, count(*) as n')->groupBy('application_id')->pluck('n', 'application_id');

        return response()->json(['data' => $apps->map(fn (Application $a): array => $this->present($a, $names[$a->id] ?? [], (int) ($counts[$a->id] ?? 0), $manage))->values()]);
    }

    public function show(Application $application): JsonResponse
    {
        Gate::authorize('system.manage_applications');

        return response()->json(['data' => $this->present($application, ['name' => $application->translate('name'), 'description' => $application->translate('description')], 0, true) + [
            'names' => $application->translationsFor('name'),
            'descriptions' => $application->translationsFor('description'),
            'maintenance_messages' => $application->translationsFor('maintenance_message'),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_applications');
        $data = $this->validated($request, null);
        $app = DB::transaction(function () use ($data): Application {
            $app = Application::query()->create([
                'key' => $data['key'], 'icon' => $data['icon'] ?? null, 'color' => $data['color'] ?? null, 'status' => 'active',
                'data_sharing_default' => $data['data_sharing_default'] ?? 'shared', 'maintenance_mode' => false,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            $app->setTranslations('name', $data['name']);
            $app->setTranslations('description', $data['description'] ?? []);
            $this->permissions->registerApplication($app->id, $app->uuid);

            return $app;
        });

        return response()->json(['data' => ['uuid' => $app->uuid]], 201);
    }

    public function update(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('system.manage_applications');
        $data = $this->validated($request, $application);
        $application->fill(array_intersect_key($data, array_flip(['key', 'icon', 'color', 'data_sharing_default', 'sort_order'])))->save();
        foreach (['name', 'description'] as $f) {
            if (isset($data[$f])) {
                $application->setTranslations($f, $data[$f]);
            }
        }

        return response()->json(['data' => ['uuid' => $application->uuid]]);
    }

    public function status(Request $request, Application $application, string $action): JsonResponse
    {
        Gate::authorize('system.manage_applications');
        $status = match ($action) {
            'archive' => 'archived',
            'retire' => 'retired',
            'activate' => 'active',
            default => abort(404),
        };
        $application->forceFill(['status' => $status])->save();

        return response()->json(['data' => ['status' => $status]]);
    }

    public function maintenance(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('system.manage_applications');
        Gate::authorize('system.enable_maintenance_mode');
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'until' => ['sometimes', 'nullable', 'date', 'after:now'],
            'message' => ['sometimes', 'array'],
            'message.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $application->forceFill(['maintenance_mode' => $data['enabled'], 'maintenance_until' => $data['until'] ?? null])->save();
        if (isset($data['message'])) {
            $application->setTranslations('maintenance_message', $data['message']);
        }

        return response()->json(['data' => ['maintenance_mode' => $application->maintenance_mode]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Application $app): array
    {
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $r = $app === null ? 'required' : 'sometimes';

        return $request->validate([
            'key' => [$r, 'string', 'regex:/^[a-z][a-z0-9_]{1,63}$/', Rule::unique('applications', 'key')->where('organization_id', app(TenantContext::class)->organizationId())->ignore($app?->id)],
            'name' => [$r, 'array'],
            'name.'.$default => [$r, 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'icon' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9 -]{0,64}$/'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'data_sharing_default' => ['sometimes', Rule::in(['shared', 'isolated'])],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Application $a, array $names, int $forms, bool $manage): array
    {
        return [
            'uuid' => $a->uuid, 'key' => $a->key, 'name' => $names['name'] ?? Translator::humanize((string) $a->key), 'description' => $names['description'] ?? null,
            'icon' => $a->icon, 'color' => $a->color, 'status' => $a->status, 'data_sharing_default' => $a->data_sharing_default,
            'maintenance_mode' => $a->maintenance_mode, 'maintenance_until' => $a->maintenance_until?->toIso8601String(),
            'sort_order' => $a->sort_order, 'forms' => $manage ? $forms : null,
        ];
    }
}
