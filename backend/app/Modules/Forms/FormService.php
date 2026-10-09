<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\Modules\Access\AccessCache;
use App\Modules\Access\FieldAccessRules;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Collection;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormVersion;
use App\Modules\Forms\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Form and collection lifecycle (specification §4.8, §4.10, §4.13): create,
 * unpublish/archive/republish without data loss, load an older version into
 * the draft for rollback, duplicate.
 */
final class FormService
{
    public const DEFAULT_SETTINGS = [
        'autosaveDrafts' => true,
        'allowComments' => true,
        'allowAttachments' => true,
        'conflictResolution' => 'field_by_field',
        'afterSubmit' => ['redirect' => 'view'],
        'modes' => ['create' => true, 'edit' => true, 'view' => true, 'print' => true],
    ];

    public function __construct(
        private readonly FormTables $tables,
        private readonly ObjectPermissions $permissions,
        private readonly DraftRepository $drafts,
        private readonly PublishedDefinitions $definitions,
        private readonly AuditWriter $audit,
        private readonly AccessCache $accessCache,
    ) {}

    /**
     * @param  array{kind: string, key: string, application: Application, name: array<string, string>, description?: array<string, string>, binding_mode?: string, bound_table?: string|null, collection_type?: string, icon?: string|null}  $data
     */
    public function create(array $data): Form
    {
        return DB::transaction(function () use ($data): Form {
            $form = new Form([
                'application_id' => $data['application']->id,
                'kind' => $data['kind'],
                'key' => $data['key'],
                'binding_mode' => $data['binding_mode'] ?? 'managed',
                'state' => 'draft',
                'draft_version_number' => 1,
                'icon' => $data['icon'] ?? null,
                'data_sharing' => $data['application']->data_sharing_default,
                'workflow_enabled' => false,
                'settings' => self::DEFAULT_SETTINGS,
                'record_count_cache' => 0,
                'table_name' => '',
            ]);
            $form->organization_id = app(TenantContext::class)->organizationId();
            if ($form->binding_mode === 'bound') {
                $table = (string) ($data['bound_table'] ?? '');
                if (! in_array($table, $this->tables->bindableTables(), true)) {
                    throw ValidationException::withMessages(['bound_table' => __('forms.bound_table_unavailable')]);
                }
                $form->table_name = $table;
            } else {
                $form->table_name = $this->tables->tableFor($form, $data['key']);
                if (! $this->tables->isAvailable($form->table_name)) {
                    throw ValidationException::withMessages(['key' => __('forms.table_name_taken', ['table' => $form->table_name])]);
                }
            }
            $form->save();
            $form->setTranslations('name', $data['name']);
            if (isset($data['description'])) {
                $form->setTranslations('description', $data['description']);
            }
            if ($form->kind === 'collection') {
                Collection::query()->create(['form_id' => $form->id, 'collection_type' => $data['collection_type'] ?? 'table', 'is_shared_reference' => false]);
            }
            $this->permissions->registerForm($form->id, $form->uuid);

            return $form;
        });
    }

    /** published ⇄ unpublished, → archived, archived/unpublished → published (records are never touched). */
    public function changeState(Form $form, string $action): Form
    {
        $target = match ($action) {
            'unpublish' => 'unpublished',
            'archive' => 'archived',
            'republish' => 'published',
            default => throw ValidationException::withMessages(['action' => 'Unknown action.']),
        };
        if ($form->current_version_id === null) {
            throw ValidationException::withMessages(['action' => __('forms.never_published')]);
        }
        if ($form->state === 'schema_inconsistent') {
            throw ValidationException::withMessages(['action' => __('forms.schema_inconsistent')]);
        }
        $before = $form->state;
        $form->forceFill(['state' => $target])->save();
        MenuItem::query()->where('target_type', 'form')->where('target_id', $form->id)->update(['is_active' => $target === 'published']);
        $this->audit->record('form.'.$action, 'schema', [['field_key' => 'state', 'old' => $before, 'new' => $target]], 'form', $form->id);
        $this->accessCache->bump();

        return $form;
    }

    /**
     * Replaces the draft with an older published version (rollback is then a
     * normal publish of that draft, architecture §13.4).
     */
    public function loadVersionIntoDraft(Form $form, FormVersion $version): void
    {
        $doc = $version->definition;
        unset($doc['$schema'], $doc['schema'], $doc['targets'], $doc['access']);
        $doc['form']['key'] = $form->key;
        $doc['form']['bindingMode'] = $form->binding_mode;
        unset($doc['form']['table'], $doc['form']['version']);
        $published = $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
        $this->drafts->save($form, $doc, null, $published);
        $this->audit->record('form.version_loaded_into_draft', 'config', null, 'form', $form->id, ['version' => $version->version_number]);
    }

    /**
     * A new draft form with the same structure (new uuids and keys), and
     * optionally the same access rules (specification §4.30).
     */
    public function duplicate(Form $source, string $key, array $name, bool $withPermissions): Form
    {
        $application = Application::query()->findOrFail($source->application_id);
        $copy = $this->create([
            'kind' => $source->kind, 'key' => $key, 'application' => $application, 'name' => $name,
            'collection_type' => $source->collection->collection_type ?? 'table',
        ]);
        $doc = $this->drafts->load($source);
        $doc = $this->drafts->normalize($doc);
        $map = [];
        $fresh = static function (string $old) use (&$map): string {
            return $map[$old] ??= (string) Str::uuid7();
        };
        $doc = $this->remapUuids($doc, $fresh, [$source->uuid => $copy->uuid]);
        $doc['form']['key'] = $key;
        $doc['form']['bindingMode'] = 'managed';
        $doc['form']['boundTable'] = null;
        $doc['form']['i18n']['name'] = $name;
        $this->drafts->save($copy, $doc, null, null);
        if ($withPermissions) {
            $this->copyAccessRules($source, $copy, $map);
        }

        return $copy;
    }

    /**
     * Gives every uuid inside a draft document a new value, consistently, so the
     * copy references its own groups, fields, options, relations and
     * conditions. Uuids of other forms (relation targets) are kept.
     *
     * @param  array<string, mixed>  $doc
     * @param  callable(string): string  $fresh
     * @param  array<string, string>  $fixed
     * @return array<string, mixed>
     */
    public function remapUuids(array $doc, callable $fresh, array $fixed = []): array
    {
        $own = [];
        foreach (['groups', 'fields', 'relations', 'conditions'] as $kind) {
            foreach ($doc[$kind] as $o) {
                $own[$o['uuid']] = true;
                foreach ($o['options']['static'] ?? [] as $opt) {
                    $own[$opt['uuid']] = true;
                }
            }
        }
        $map = $fixed;
        foreach (array_keys($own) as $uuid) {
            $map[$uuid] = $fresh($uuid);
        }
        $walk = static function (mixed $v) use (&$walk, $map): mixed {
            if (is_string($v)) {
                return $map[$v] ?? $v;
            }
            if (is_array($v)) {
                $out = [];
                foreach ($v as $k => $item) {
                    $out[$k] = $walk($item);
                }

                return $out;
            }

            return $v;
        };

        return $walk($doc);
    }

    /** @param  array<string, string>  $map  old uuid => new uuid */
    private function copyAccessRules(Form $source, Form $copy, array $map): void
    {
        $groupIds = DB::table('field_groups')->where('form_id', $copy->id)->pluck('id', 'uuid')->all();
        $fieldIds = DB::table('fields')->where('form_id', $copy->id)->pluck('id', 'uuid')->all();
        $oldGroups = DB::table('field_groups')->where('form_id', $source->id)->pluck('uuid', 'id')->all();
        $oldFields = DB::table('fields')->where('form_id', $source->id)->pluck('uuid', 'id')->all();
        foreach (DB::table('field_access_rules')->where('form_id', $source->id)->get() as $r) {
            $groupId = $r->group_id === null ? null : ($groupIds[$map[strtolower((string) $oldGroups[$r->group_id])] ?? ''] ?? null);
            $fieldId = $r->field_id === null ? null : ($fieldIds[$map[strtolower((string) $oldFields[$r->field_id])] ?? ''] ?? null);
            if (($r->group_id !== null && $groupId === null) || ($r->field_id !== null && $fieldId === null)) {
                continue;
            }
            app(FieldAccessRules::class)->put($copy, $r->target_type, $groupId, $fieldId, $r->subject_type, $r->subject_id === null ? null : (int) $r->subject_id, $r->mode, $r->access, $r->effect);
        }
        foreach (DB::table('permission_assignments')->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->where('permissions.key', 'like', "form.{$source->uuid}.%")->get(['permissions.key', 'subject_type', 'subject_id', 'effect', 'include_descendants']) as $a) {
            $key = str_replace($source->uuid, $copy->uuid, (string) $a->key);
            $pid = DB::table('permissions')->where('key', $key)->value('id');
            if ($pid !== null && ! DB::table('permission_assignments')->where(['permission_id' => $pid, 'subject_type' => $a->subject_type, 'subject_id' => $a->subject_id])->exists()) {
                PermissionAssignment::query()->create(['permission_id' => $pid, 'subject_type' => $a->subject_type, 'subject_id' => $a->subject_id, 'effect' => $a->effect, 'include_descendants' => $a->include_descendants]);
            }
        }
        $this->accessCache->bump();
    }
}
