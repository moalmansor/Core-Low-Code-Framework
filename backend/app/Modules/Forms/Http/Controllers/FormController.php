<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\Models\Role;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Definition\ClientDefinition;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Draft\DraftConflict;
use App\Modules\Forms\Draft\DraftInvalid;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\FormService;
use App\Modules\Forms\Jobs\RunMigrationPlan;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormVersion;
use App\Modules\Forms\Publishing\PublishBlocked;
use App\Modules\Forms\Publishing\PublishService;
use App\Modules\Forms\Publishing\VersionDiff;
use App\Modules\Identity\Models\User;
use App\Modules\Schema\Models\MigrationPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Form builder API (architecture §21.2 "Forms & collections"): forms and
 * collections, the draft document, preview, impact analysis, publish,
 * versions, diff, rollback, state changes and duplication. Every endpoint
 * requires Manage Forms.
 */
final class FormController extends Controller
{
    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $validator,
        private readonly PublishedDefinitions $definitions,
        private readonly PublishService $publisher,
        private readonly FormService $forms,
        private readonly Translator $translator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'kind' => ['sometimes', Rule::in(['form', 'collection'])],
            'application' => ['sometimes', 'uuid'],
            'state' => ['sometimes', Rule::in(['draft', 'published', 'unpublished', 'archived', 'schema_inconsistent'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = Form::query()->with('application:id,uuid,key')->orderBy('key');
        if (isset($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (isset($data['state'])) {
            $query->where('state', $data['state']);
        }
        if (isset($data['application'])) {
            $query->whereIn('application_id', Application::query()->where('uuid', $data['application'])->select('id'));
        }
        if (! empty($data['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['search']).'%';
            $ids = DB::table('translations')->where('object_type', 'form')->where('field', 'name')->where('value', 'like', $term)->pluck('object_id');
            $query->where(static fn ($q) => $q->where('key', 'like', $term)->orWhereIn('id', $ids));
        }
        $page = $query->paginate($data['per_page'] ?? 25);
        $names = $this->translator->many('form', $page->getCollection()->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name']);
        $versions = FormVersion::query()->whereIn('id', $page->getCollection()->pluck('current_version_id')->filter())->pluck('version_number', 'id')->all();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Form $f): array => $this->summary($f, $names[$f->id]['name'] ?? $f->key, $versions[$f->current_version_id] ?? null))->values(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $data = $request->validate([
            'kind' => ['required', Rule::in(['form', 'collection'])],
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,39}$/', Rule::unique('forms', 'key')->where('organization_id', app(TenantContext::class)->organizationId())],
            'application' => ['required', 'uuid', Rule::exists('applications', 'uuid')->whereNull('deleted_at')],
            'name' => ['required', 'array'],
            'name.'.$default => ['required', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'binding_mode' => ['sometimes', Rule::in(['managed', 'bound'])],
            'bound_table' => ['required_if:binding_mode,bound', 'nullable', 'string', 'max:60'],
            'collection_type' => ['sometimes', Rule::in(['key_value', 'table'])],
        ]);
        $form = $this->forms->create(['application' => Application::query()->where('uuid', $data['application'])->firstOrFail()] + $data);

        return response()->json(['data' => ['uuid' => $form->uuid, 'key' => $form->key, 'table_name' => $form->table_name]], 201);
    }

    public function show(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $name = $form->translate('name') ?? $form->key;
        $version = $form->current_version_id === null ? null : FormVersion::query()->whereKey($form->current_version_id)->value('version_number');
        $plan = MigrationPlan::query()->where('form_id', $form->id)->orderByDesc('id')->first();

        return response()->json(['data' => $this->summary($form, $name, $version) + [
            'names' => $form->translationsFor('name'),
            'descriptions' => $form->translationsFor('description'),
            'draft_updated_at' => $this->drafts->draftStamp($form),
            'last_plan' => $plan === null ? null : ['uuid' => $plan->uuid, 'status' => $plan->status, 'purpose' => $plan->purpose, 'error' => $plan->error],
        ]]);
    }

    public function destroy(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        abort_if($form->current_version_id !== null, 422, __('forms.delete_published'));
        DB::transaction(function () use ($form): void {
            app(ObjectPermissions::class)->forget('form.'.$form->uuid);
            DB::table('field_access_rules')->where('form_id', $form->id)->delete();
            DB::table('conditions')->where('form_id', $form->id)->delete();
            DB::table('field_options')->whereIn('field_id', DB::table('fields')->where('form_id', $form->id)->select('id'))->delete();
            DB::table('relations')->where('source_form_id', $form->id)->update(['display_field_id' => null, 'value_field_id' => null]);
            DB::table('collections')->where('form_id', $form->id)->delete();
            DB::table('field_groups')->where('form_id', $form->id)->update(['relation_id' => null, 'parent_group_id' => null]);
            DB::table('fields')->where('form_id', $form->id)->update(['relation_id' => null, 'group_id' => null]);
            DB::table('relations')->where('source_form_id', $form->id)->delete();
            DB::table('fields')->where('form_id', $form->id)->delete();
            DB::table('field_groups')->where('form_id', $form->id)->delete();
            $form->forceDelete();
        });

        return response()->json(null, 204);
    }

    public function draft(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => [
            'document' => $this->drafts->load($form),
            'draft_updated_at' => $this->drafts->draftStamp($form),
            'problems' => $this->drafts->problems($form, $this->published($form)),
        ]]);
    }

    public function saveDraft(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'document' => ['required', 'array'],
            'draft_updated_at' => ['present', 'nullable', 'string'],
        ]);
        abort_if($form->state === 'schema_inconsistent', 423, __('forms.schema_inconsistent'));
        $this->guardHooks($form, $data['document']);
        try {
            $result = $this->drafts->save($form, $data['document'], $data['draft_updated_at'], $this->published($form));
        } catch (DraftInvalid $e) {
            return response()->json(['message' => __('forms.draft_invalid'), 'code' => 'draft_invalid', 'errors' => $e->errors], 422);
        } catch (DraftConflict $e) {
            return response()->json([
                'message' => __('forms.draft_conflict'), 'code' => 'draft_conflict',
                'current_draft_updated_at' => $e->currentUpdatedAt,
                'updated_by' => $e->updatedBy === null ? null : User::query()->whereKey($e->updatedBy)->value('name'),
            ], 409);
        }

        return response()->json(['data' => $result]);
    }

    public function validateDraft(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['document' => ['required', 'array']]);

        return response()->json(['data' => $this->validator->validate($this->drafts->normalize($data['document']), $form, $this->published($form))]);
    }

    /** The compiled draft as a given user or role would see it (specification §4.3 live preview). */
    public function preview(Request $request, Form $form, DefinitionCompiler $compiler, FieldAccessResolver $access, ClientDefinition $client): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'mode' => ['sometimes', Rule::in(['create', 'edit', 'view', 'print'])],
            'as_user' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'uuid')],
            'as_role' => ['sometimes', 'nullable', 'uuid', Rule::exists('roles', 'uuid')],
        ]);
        $mode = $data['mode'] ?? 'create';
        $compiled = $compiler->compile($form, (int) $form->draft_version_number);
        $definition = $compiled['definition'];
        if (! empty($data['as_role'])) {
            $role = Role::query()->where('uuid', $data['as_role'])->firstOrFail();
            $levels = $access->resolveForRole($role->id, $form->id, $form->uuid, $definition, $mode);
        } else {
            $user = ! empty($data['as_user']) ? User::query()->where('uuid', $data['as_user'])->firstOrFail() : Auth::user();
            $levels = $access->resolve($user, $form->id, $form->uuid, $definition + ['form' => $definition['form'] + ['version' => 'draft-'.$form->draft_updated_at?->getTimestamp()]], $mode);
        }

        return response()->json(['data' => ['definition' => $client->build($definition, $levels, $mode), 'problems' => $compiled['problems']]]);
    }

    public function impact(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        try {
            $prepared = $this->publisher->prepare($form);
        } catch (PublishBlocked $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], 423);
        }

        return response()->json(['data' => [
            'impact' => $prepared['impact'],
            'impact_hash' => $prepared['hash'],
            'diff' => $prepared['diff'],
            'version' => (int) $form->draft_version_number,
        ]]);
    }

    public function publish(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $data = $request->validate([
            'impact_hash' => ['required', 'string', 'size:64'],
            'confirm_blocking' => ['sometimes', 'boolean'],
            'confirm_destructive' => ['sometimes', 'boolean'],
            'typed_confirmation' => ['sometimes', 'nullable', 'string', 'max:64'],
            'change_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'placement' => ['sometimes', 'nullable', 'array'],
            'placement.application' => ['required_with:placement', 'uuid', Rule::exists('applications', 'uuid')->whereNull('deleted_at')],
            'placement.parent' => ['sometimes', 'nullable', 'uuid', Rule::exists('menu_items', 'uuid')],
            'placement.sort_order' => ['sometimes', 'integer', 'between:0,100000'],
            'placement.icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'placement.label' => ['required_with:placement', 'array'],
            'placement.label.'.$default => ['required_with:placement', 'string', 'max:255'],
            'placement.label.*' => ['nullable', 'string', 'max:255'],
            'allowed' => ['sometimes', 'nullable', 'array'],
            'allowed.roles' => ['sometimes', 'array'],
            'allowed.roles.*' => ['uuid', Rule::exists('roles', 'uuid')],
            'allowed.users' => ['sometimes', 'array'],
            'allowed.users.*' => ['uuid', Rule::exists('users', 'uuid')],
            'allowed.departments' => ['sometimes', 'array'],
            'allowed.departments.*' => ['uuid', Rule::exists('departments', 'uuid')],
            'rollback_of' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $rollbackOf = isset($data['rollback_of']) ? FormVersion::query()->where('form_id', $form->id)->where('version_number', $data['rollback_of'])->value('id') : null;
        if (! empty($data['placement'])) {
            Gate::authorize('system.manage_pages_menus');
        }
        if (! empty(array_filter($data['allowed'] ?? []))) {
            Gate::authorize('system.manage_permissions');
        }
        try {
            $plan = $this->publisher->start($form, (int) Auth::id(), $data + ['purpose' => $rollbackOf === null ? 'publish' : 'rollback', 'rollback_of' => $rollbackOf, 'placement' => ['menu' => $data['placement'] ?? null, 'allowed' => $data['allowed'] ?? null]]);
        } catch (PublishBlocked $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->reason === 'impact_changed' ? 409 : 422);
        }
        RunMigrationPlan::dispatch($plan->id);

        return response()->json(['data' => ['plan' => $plan->uuid, 'status' => $plan->refresh()->status]], 202);
    }

    public function versions(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $versions = FormVersion::query()->where('form_id', $form->id)->orderByDesc('version_number')
            ->get(['id', 'uuid', 'version_number', 'state', 'change_class', 'published_at', 'published_by', 'change_note', 'rollback_of_version_id', 'diff_from_previous']);
        $users = User::query()->whereIn('id', $versions->pluck('published_by'))->pluck('name', 'id');
        $numbers = $versions->pluck('version_number', 'id');

        return response()->json(['data' => $versions->map(static fn (FormVersion $v): array => [
            'uuid' => $v->uuid,
            'version' => $v->version_number,
            'state' => $v->state,
            'change_class' => $v->change_class,
            'published_at' => $v->published_at->toIso8601String(),
            'published_by' => $users[$v->published_by] ?? null,
            'change_note' => $v->change_note,
            'rollback_of' => $v->rollback_of_version_id === null ? null : ($numbers[$v->rollback_of_version_id] ?? null),
            'summary' => $v->diff_from_previous['summary'] ?? null,
        ])->values()]);
    }

    public function version(Form $form, int $number): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $v = FormVersion::query()->where('form_id', $form->id)->where('version_number', $number)->firstOrFail();

        return response()->json(['data' => ['version' => $v->version_number, 'state' => $v->state, 'definition' => $v->definition, 'impact_report' => $v->impact_report, 'diff_from_previous' => $v->diff_from_previous]]);
    }

    public function diff(Form $form, string $from, string $to, VersionDiff $differ): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $load = function (string $n) use ($form): array {
            if ($n === 'draft') {
                return app(DefinitionCompiler::class)->compile($form, (int) $form->draft_version_number)['definition'];
            }

            return FormVersion::query()->where('form_id', $form->id)->where('version_number', (int) $n)->firstOrFail()->definition;
        };

        return response()->json(['data' => $differ->diff($load($from), $load($to))]);
    }

    /** Loads a version into the draft; the admin then reviews the impact and publishes it as a rollback. */
    public function rollback(Request $request, Form $form, int $number): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['discard_draft' => ['sometimes', 'boolean']]);
        $version = FormVersion::query()->where('form_id', $form->id)->where('version_number', $number)->firstOrFail();
        abort_if($version->id === $form->current_version_id, 422, __('forms.rollback_current'));
        if ($this->hasUnpublishedChanges($form) && ! ($data['discard_draft'] ?? false)) {
            return response()->json(['message' => __('forms.rollback_discards_draft'), 'code' => 'draft_has_changes'], 409);
        }
        $this->forms->loadVersionIntoDraft($form, $version);

        return response()->json(['data' => ['rollback_of' => $number, 'draft_updated_at' => $this->drafts->draftStamp($form->refresh())]]);
    }

    public function changeState(Request $request, Form $form, string $action): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        abort_unless(in_array($action, ['unpublish', 'archive', 'republish'], true), 404);
        $this->forms->changeState($form, $action);

        return response()->json(['data' => ['state' => $form->state]]);
    }

    public function duplicate(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,39}$/', Rule::unique('forms', 'key')->where('organization_id', $form->organization_id)],
            'name' => ['required', 'array'],
            'name.'.$default => ['required', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'include' => ['sometimes', Rule::in(['structure', 'structure_permissions', 'everything'])],
        ]);
        $include = $data['include'] ?? 'structure';
        if ($include !== 'structure') {
            Gate::authorize('system.manage_permissions');
        }
        $copy = $this->forms->duplicate($form, $data['key'], $data['name'], $include !== 'structure');

        return response()->json(['data' => ['uuid' => $copy->uuid, 'key' => $copy->key]], 201);
    }

    /** @return array<string, mixed> */
    private function summary(Form $f, string $name, ?int $version): array
    {
        return [
            'uuid' => $f->uuid,
            'key' => $f->key,
            'kind' => $f->kind,
            'name' => $name,
            'state' => $f->state,
            'binding_mode' => $f->binding_mode,
            'table_name' => $f->table_name,
            'icon' => $f->icon,
            'version' => $version,
            'next_version' => $f->draft_version_number,
            'application' => $f->application === null ? null : ['uuid' => $f->application->uuid, 'key' => $f->application->key],
            'record_count' => $f->record_count_cache,
            'updated_at' => $f->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function published(Form $form): ?array
    {
        return $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
    }

    private function hasUnpublishedChanges(Form $form): bool
    {
        $published = $this->published($form);
        if ($published === null) {
            return true;
        }
        $draft = app(DefinitionCompiler::class)->compile($form, (int) $published['form']['version'])['definition'];
        $strip = static fn (array $d): array => array_diff_key($d, array_flip(['access', 'targets', 'schema']));

        return DefinitionCompiler::hash($strip($draft)) !== DefinitionCompiler::hash($strip($published));
    }

    /** Developer hooks may only be changed by holders of Manage Code (specification §4.6 Events). */
    private function guardHooks(Form $form, array $document): void
    {
        $current = DB::table('fields')->where('form_id', $form->id)->whereNotNull('hook_binding')->pluck('hook_binding', 'uuid')
            ->map(static fn ($h) => json_decode((string) $h, true))->all();
        $seen = [];
        foreach ($document['fields'] ?? [] as $f) {
            $seen[$f['uuid'] ?? ''] = true;
            $new = $f['hook'] ?? null;
            $old = $current[$f['uuid'] ?? ''] ?? null;
            if ($new != $old) {
                Gate::authorize('system.manage_code');
            }
        }
        if (array_diff_key($current, $seen) !== []) {
            Gate::authorize('system.manage_code');
        }
    }
}
