<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\FieldTemplate;
use App\Support\Json\SchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The reusable field library (specification §4.3): a configured field or a
 * group subtree saved in the draft format; uuids and keys are regenerated
 * when it is inserted into a form.
 */
final class FieldTemplateController extends Controller
{
    public function __construct(private readonly Translator $translator, private readonly SchemaValidator $schemas) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['kind' => ['sometimes', Rule::in(['field', 'group'])], 'category' => ['sometimes', 'nullable', 'string', 'max:64'], 'application' => ['sometimes', 'uuid']]);
        $query = FieldTemplate::query()->orderBy('category')->orderByDesc('usage_count');
        if (isset($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }
        if (isset($data['application'])) {
            $appId = Application::query()->where('uuid', $data['application'])->value('id');
            $query->where(static fn ($q) => $q->whereNull('application_id')->orWhere('application_id', $appId));
        }
        $templates = $query->get();
        $names = $this->translator->many('field_template', $templates->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name', 'description']);

        return response()->json(['data' => $templates->map(fn (FieldTemplate $t): array => [
            'uuid' => $t->uuid, 'kind' => $t->kind, 'category' => $t->category,
            'name' => $names[$t->id]['name'] ?? $t->uuid, 'description' => $names[$t->id]['description'] ?? null,
            'names' => $t->translationsFor('name'),
            'usage_count' => $t->usage_count, 'definition' => $t->definition,
            'application' => $t->application_id === null ? null : Application::query()->whereKey($t->application_id)->value('uuid'),
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $this->validated($request, true);
        $template = FieldTemplate::query()->create([
            'kind' => $data['kind'], 'category' => $data['category'] ?? null, 'definition' => $data['definition'],
            'application_id' => isset($data['application']) ? Application::query()->where('uuid', $data['application'])->value('id') : null,
            'usage_count' => 0,
        ]);
        $template->setTranslations('name', $data['name']);
        $template->setTranslations('description', $data['description'] ?? []);

        return response()->json(['data' => ['uuid' => $template->uuid]], 201);
    }

    public function update(Request $request, FieldTemplate $template): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $this->validated($request, false, $template->kind);
        $template->fill(array_intersect_key($data, array_flip(['category', 'definition'])))->save();
        if (isset($data['name'])) {
            $template->setTranslations('name', $data['name']);
        }
        if (isset($data['description'])) {
            $template->setTranslations('description', $data['description']);
        }

        return response()->json(['data' => ['uuid' => $template->uuid]]);
    }

    public function used(FieldTemplate $template): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $template->increment('usage_count');

        return response()->json(['data' => ['usage_count' => $template->usage_count]]);
    }

    public function destroy(FieldTemplate $template): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        DB::table('fields')->where('template_id', $template->id)->update(['template_id' => null]);
        $template->delete();
        $this->translator->forget('field_template', $template->id);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating, ?string $kind = null): array
    {
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $r = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'kind' => [$creating ? 'required' : 'prohibited', Rule::in(['field', 'group'])],
            'category' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9_]{1,64}$/'],
            'application' => ['sometimes', 'nullable', 'uuid', Rule::exists('applications', 'uuid')],
            'name' => [$r, 'array'],
            'name.'.$default => [$r, 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'definition' => [$r, 'array'],
            'definition.groups' => ['sometimes', 'array'],
            'definition.fields' => ['sometimes', 'array'],
            'definition.conditions' => ['sometimes', 'array'],
        ]);
        if (isset($data['definition'])) {
            // A template is a fragment of a draft document: validate it as one.
            $u = '00000000-0000-7000-8000-000000000000';
            $doc = ['form' => ['uuid' => $u, 'key' => 'template', 'kind' => 'form'], 'groups' => $data['definition']['groups'] ?? [], 'fields' => $data['definition']['fields'] ?? [], 'relations' => [], 'conditions' => $data['definition']['conditions'] ?? []];
            $errors = $this->schemas->errors('https://schemas.core-lcf/form-draft/v1', $doc);
            if ($errors !== []) {
                throw ValidationException::withMessages(collect($errors)->mapWithKeys(static fn ($m, $p) => ['definition'.str_replace('/', '.', $p) => $m])->all());
            }
            if (($data['kind'] ?? $kind) === 'field' && count($doc['fields']) !== 1) {
                throw ValidationException::withMessages(['definition.fields' => __('forms.template_single_field')]);
            }
        }

        return $data;
    }
}
