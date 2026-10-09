<?php

declare(strict_types=1);

namespace App\Modules\Blueprints\Http\Controllers;

use App\Modules\Blueprints\BlueprintService;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\BlueprintInstance;
use App\Modules\Blueprints\Models\BlueprintVersion;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Views\ViewBlueprints;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Blueprints API (architecture §21.2 "Blueprints"). Every endpoint needs
 * Manage Blueprints; creating a form from a blueprint also needs Manage
 * Forms, and carrying permissions needs Manage Permissions.
 */
final class BlueprintController extends Controller
{
    private const INCLUDE = ['structure', 'structure_permissions', 'everything'];

    public function __construct(private readonly BlueprintService $blueprints, private readonly Translator $translator) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $data = $request->validate([
            'kind' => ['sometimes', Rule::in(['form', 'collection', 'workflow', 'view', 'action', 'notification', 'dashboard', 'application'])],
            'category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'library' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $q = Blueprint::query()->with('currentVersion:id,version,include_mode,created_at')->orderBy('id', 'desc');
        if (isset($data['kind'])) {
            $q->where('kind', $data['kind']);
        }
        if (($data['category'] ?? null) !== null) {
            $q->where('category', $data['category']);
        }
        if (isset($data['library'])) {
            $q->where('is_library', (bool) $data['library']);
        }
        $items = $q->get();
        $names = $this->translator->many('blueprint', $items->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name', 'description']);
        $instances = DB::table('blueprint_instances')->where('is_detached', false)->selectRaw('blueprint_id, count(*) as n')->groupBy('blueprint_id')->pluck('n', 'blueprint_id');
        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $rows = $items->map(fn (Blueprint $b) => $this->present($b, $names[$b->id] ?? [], (int) ($instances[$b->id] ?? 0)))
            ->filter(static fn (array $r) => $search === '' || str_contains(mb_strtolower(($r['name'] ?? '').' '.($r['description'] ?? '').' '.implode(' ', $r['tags']).' '.($r['category'] ?? '')), $search))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function show(Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $instances = BlueprintInstance::query()->where('blueprint_id', $blueprint->id)->orderBy('id')->get();
        $views = $instances->where('object_type', 'view')->isEmpty() ? collect() : DB::table('views')->whereIn('id', $instances->where('object_type', 'view')->pluck('object_id'))->get(['id', 'uuid', 'key', 'form_id'])->keyBy('id');
        $forms = Form::query()->whereIn('id', [...$instances->where('object_type', 'form')->pluck('object_id'), ...$views->pluck('form_id')])->get()->keyBy('id');
        $versionNumbers = BlueprintVersion::query()->where('blueprint_id', $blueprint->id)->pluck('version', 'id');

        return response()->json(['data' => $this->present($blueprint, ['name' => $blueprint->translate('name'), 'description' => $blueprint->translate('description')], $instances->where('is_detached', false)->count()) + [
            'names' => $blueprint->translationsFor('name'),
            'descriptions' => $blueprint->translationsFor('description'),
            'source' => match ($blueprint->source_type) {
                'form' => Form::query()->whereKey($blueprint->source_id)->first(['uuid', 'key'])?->only(['uuid', 'key']),
                'view' => ($v = DB::table('views')->where('id', $blueprint->source_id)->first(['uuid', 'key'])) === null ? null : ['uuid' => strtolower((string) $v->uuid), 'key' => $v->key, 'type' => 'view'],
                default => null,
            },
            'versions' => BlueprintVersion::query()->where('blueprint_id', $blueprint->id)->orderByDesc('version')->get(['id', 'uuid', 'version', 'include_mode', 'changelog', 'content_hash', 'created_at', 'created_by'])
                ->map(static fn (BlueprintVersion $v) => ['uuid' => $v->uuid, 'version' => $v->version, 'include_mode' => $v->include_mode, 'changelog' => $v->changelog, 'created_at' => $v->created_at?->toIso8601ZuluString()]),
            'instances' => $instances->map(static fn (BlueprintInstance $i) => [
                'uuid' => $i->uuid, 'detached' => $i->is_detached, 'include_mode' => $i->include_mode,
                'version' => $versionNumbers[$i->baseVersionId()] ?? null,
                'form' => ($f = $forms[$i->object_type === 'view' ? ($views[$i->object_id]->form_id ?? 0) : $i->object_id] ?? null) === null ? null : ['uuid' => $f->uuid, 'key' => $f->key, 'name' => $f->translate('name'), 'state' => $f->state],
                'view' => $i->object_type === 'view' && isset($views[$i->object_id]) ? ['uuid' => strtolower((string) $views[$i->object_id]->uuid), 'key' => $views[$i->object_id]->key] : null,
            ])->values(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $data = $request->validate($this->metaRules(true) + [
            'source' => ['required', 'uuid'],
            'source_type' => ['sometimes', Rule::in(['form', 'view'])],
            'include_mode' => ['required_unless:source_type,view', Rule::in(self::INCLUDE)],
        ]);
        if (($data['source_type'] ?? 'form') === 'view') {
            Gate::authorize('system.manage_forms');
            $bp = app(ViewBlueprints::class)->create(strtolower($data['source']), $data);

            return response()->json(['data' => ['uuid' => $bp->uuid]], 201);
        }
        $form = Form::query()->where('uuid', $data['source'])->firstOrFail();
        $this->authorizeInclude($data['include_mode']);
        $bp = $this->blueprints->createFromForm($form, $data);

        return response()->json(['data' => ['uuid' => $bp->uuid]], 201);
    }

    public function update(Request $request, Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $data = $request->validate($this->metaRules(false));
        $blueprint->fill(array_intersect_key($data, array_flip(['category', 'tags', 'is_library'])))->save();
        if (isset($data['name'])) {
            $blueprint->setTranslations('name', $data['name']);
        }
        if (isset($data['description'])) {
            $blueprint->setTranslations('description', $data['description']);
        }

        return response()->json(['data' => ['uuid' => $blueprint->uuid]]);
    }

    public function destroy(Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        // Objects created from it stay; they simply stop receiving updates.
        BlueprintInstance::query()->where('blueprint_id', $blueprint->id)->where('is_detached', false)->get()->each(fn (BlueprintInstance $i) => $this->blueprints->detach($i));
        $blueprint->delete();

        return response()->json(null, 204);
    }

    public function addVersion(Request $request, Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $data = $request->validate([
            'source' => ['sometimes', 'nullable', 'uuid'],
            'include_mode' => [$blueprint->kind === 'view' ? 'sometimes' : 'required', Rule::in(self::INCLUDE)],
            'changelog' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        if ($blueprint->kind === 'view') {
            $version = app(ViewBlueprints::class)->addVersion($blueprint, isset($data['source']) ? strtolower($data['source']) : null, $data['changelog'] ?? null);

            return response()->json(['data' => ['uuid' => $version->uuid, 'version' => $version->version]], 201);
        }
        $form = isset($data['source'])
            ? Form::query()->where('uuid', $data['source'])->firstOrFail()
            : ($blueprint->source_type === 'form' ? Form::query()->find($blueprint->source_id) : null);
        if ($form === null) {
            throw ValidationException::withMessages(['source' => __('blueprints.source_missing')]);
        }
        $this->authorizeInclude($data['include_mode']);
        $version = $this->blueprints->addVersion($blueprint, $form, $data['include_mode'], $data['changelog'] ?? null);

        return response()->json(['data' => ['uuid' => $version->uuid, 'version' => $version->version]], 201);
    }

    public function instantiate(Request $request, Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        Gate::authorize('system.manage_forms');
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        if ($blueprint->kind === 'view') {
            $data = $request->validate([
                'form' => ['required', 'uuid'],
                'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/'],
                'name' => ['required', 'array'],
                'name.'.$default => ['required', 'string', 'max:255'],
                'name.*' => ['nullable', 'string', 'max:255'],
                'version' => ['sometimes', 'integer', 'min:1'],
            ]);
            $form = Form::query()->where('uuid', strtolower($data['form']))->firstOrFail();
            $version = isset($data['version'])
                ? BlueprintVersion::query()->where('blueprint_id', $blueprint->id)->where('version', $data['version'])->firstOrFail()
                : BlueprintVersion::query()->findOrFail($blueprint->current_version_id);
            $result = app(ViewBlueprints::class)->instantiate($blueprint, $version, $form, $data['key'], array_filter($data['name'], 'is_string'));

            return response()->json(['data' => $result + ['form' => $form->uuid]], 201);
        }
        $data = $request->validate([
            'application' => ['required', 'uuid'],
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,39}$/'],
            'name' => ['required', 'array'],
            'name.'.$default => ['required', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'include_mode' => ['sometimes', Rule::in(self::INCLUDE)],
            'version' => ['sometimes', 'integer', 'min:1'],
        ]);
        if (Form::query()->where('key', $data['key'])->exists()) {
            throw ValidationException::withMessages(['key' => __('validation.unique', ['attribute' => 'key'])]);
        }
        $include = $data['include_mode'] ?? 'structure';
        $this->authorizeInclude($include);
        $application = Application::query()->where('uuid', $data['application'])->firstOrFail();
        $version = isset($data['version'])
            ? BlueprintVersion::query()->where('blueprint_id', $blueprint->id)->where('version', $data['version'])->firstOrFail()
            : BlueprintVersion::query()->findOrFail($blueprint->current_version_id);
        $result = $this->blueprints->instantiate($blueprint, $version, $application, $data['key'], array_filter($data['name'], 'is_string'), $include);

        return response()->json(['data' => ['uuid' => $result['form']->uuid, 'key' => $result['form']->key, 'skipped' => $result['skipped']]], 201);
    }

    public function preview(Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');

        return response()->json(['data' => $blueprint->kind === 'view' ? app(ViewBlueprints::class)->preview($blueprint) : $this->blueprints->preview($blueprint)]);
    }

    public function propagate(Request $request, Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['instances' => ['sometimes', 'array', 'max:500'], 'instances.*' => ['uuid']]);

        $instances = isset($data['instances']) ? array_map('strtolower', $data['instances']) : null;

        return response()->json(['data' => $blueprint->kind === 'view' ? app(ViewBlueprints::class)->propagate($blueprint, $instances) : $this->blueprints->propagate($blueprint, $instances)]);
    }

    public function detach(BlueprintInstance $instance): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $this->blueprints->detach($instance);

        return response()->json(null, 204);
    }

    public function export(Blueprint $blueprint): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $name = Str::slug((string) ($blueprint->translate('name') ?? 'blueprint')) ?: 'blueprint';

        return response()->json($this->blueprints->export($blueprint), 200, [
            'Content-Disposition' => 'attachment; filename="'.$name.'.blueprint.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function import(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_blueprints');
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        try {
            $payload = json_decode((string) $request->file('file')->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => __('blueprints.import_format')]);
        }
        $result = $this->blueprints->import(is_array($payload) ? $payload : []);

        return response()->json(['data' => ['uuid' => $result['blueprint']->uuid, 'versions_added' => $result['added']]], 201);
    }

    private function authorizeInclude(string $include): void
    {
        if ($include !== 'structure') {
            Gate::authorize('system.manage_permissions');
        }
    }

    /** @return array<string, list<mixed>> */
    private function metaRules(bool $creating): array
    {
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'array'],
            'name.'.$default => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'is_library' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, string|null>  $names
     * @return array<string, mixed>
     */
    private function present(Blueprint $b, array $names, int $instances): array
    {
        return [
            'uuid' => $b->uuid, 'kind' => $b->kind, 'category' => $b->category, 'tags' => $b->tags ?? [], 'is_library' => $b->is_library,
            'name' => $names['name'] ?? null, 'description' => $names['description'] ?? null,
            'version' => $b->currentVersion?->version, 'include_mode' => $b->currentVersion?->include_mode,
            'updated_at' => $b->currentVersion?->created_at?->toIso8601ZuluString(), 'instance_count' => $instances,
        ];
    }
}
