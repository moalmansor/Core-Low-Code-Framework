<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Access\AccessCache;
use App\Modules\Access\FieldAccessRules;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Draft\DraftInvalid;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\FormService;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormVersion;
use App\Modules\Forms\Publishing\PublishBlocked;
use App\Modules\Forms\Publishing\PublishService;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\NotificationRules;
use App\Modules\Platform\Packages\PackagePart;
use App\Modules\Workflow\WorkflowDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Forms and collections (ADR-0052): the published design (as the builder's
 * draft document), the workflow, the notification rules, field access rules
 * and permission grants. Only a form whose draft has no unpublished changes
 * is exported, so a package always carries what is live. Importing saves
 * the draft and workflow, publishes through the normal migration plan, then
 * applies rules and grants.
 */
final class FormPart implements PackagePart
{
    /** Per-environment form properties; the key travels on the object (it may be imported under another one). */
    private const LOCAL = ['key', 'bindingMode', 'boundTable', 'version', 'table'];

    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly WorkflowDocument $workflow,
        private readonly NotificationRules $notifications,
        private readonly FormService $forms,
        private readonly PublishService $publisher,
        private readonly PublishedDefinitions $definitions,
        private readonly FieldAccessRules $rules,
        private readonly AccessCache $cache,
        private readonly Translator $translator,
    ) {}

    public function type(): string
    {
        return 'form';
    }

    public function catalog(): array
    {
        return Form::query()->whereNotNull('current_version_id')->where('binding_mode', 'managed')->orderBy('key')->get()
            ->map(fn (Form $f) => ['uuid' => strtolower($f->uuid), 'key' => (string) $f->key, 'label' => $this->translator->labelOf($f->translationsFor('name'), (string) $f->key),
                'note' => $this->unpublished($f) ? __('packages.unpublished_changes') : null])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $form = Form::query()->where('uuid', $uuid)->first();
        if ($form === null) {
            return null;
        }
        if ($form->binding_mode !== 'managed' || $form->current_version_id === null || $this->unpublished($form)) {
            throw ValidationException::withMessages(['objects' => __('packages.form_not_exportable', ['form' => $this->name($form)])]);
        }
        $doc = $this->drafts->normalize($this->drafts->load($form));
        foreach (self::LOCAL as $prop) {
            unset($doc['form'][$prop]);
        }
        $app = DB::table('applications')->where('id', $form->application_id)->value('uuid');
        $notifications = $this->notifications->load($form);
        $dependencies = [strtolower((string) $app)];
        foreach ($doc['relations'] as $r) {
            if ($r['target'] !== strtolower($form->uuid)) {
                $dependencies[] = strtolower((string) $r['target']);
            }
        }
        foreach ($notifications as $rule) {
            if (($rule['template'] ?? null) !== null) {
                $dependencies[] = strtolower((string) $rule['template']);
            }
        }

        return [
            'uuid' => strtolower($form->uuid), 'key' => (string) $form->key, 'label' => $this->translator->labelOf($form->translationsFor('name'), (string) $form->key),
            'dependencies' => array_values(array_unique(array_filter($dependencies))),
            'content' => [
                'kind' => $form->kind, 'application' => strtolower((string) $app), 'collectionType' => $form->collection->collection_type ?? null,
                'document' => $doc, 'workflow' => $this->workflow->load($form), 'notifications' => $notifications, 'access' => $this->access($form),
            ],
        ];
    }

    public function exists(string $uuid): bool
    {
        return Form::query()->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return Form::query()->where('key', $key)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $c = $object['content'];
        $notes = [];
        $application = Application::query()->where('uuid', $c['application'])->first() ?? throw new RuntimeException(__('packages.missing_application'));
        $form = Form::query()->where('uuid', $object['uuid'])->first();
        if ($form === null) {
            $form = $this->forms->create([
                'uuid' => $object['uuid'], 'kind' => (string) $c['kind'], 'key' => $object['key'], 'application' => $application,
                'name' => (array) ($c['document']['form']['i18n']['name'] ?? []), 'collection_type' => $c['collectionType'] ?? 'table',
            ]);
        } elseif ($form->key !== $object['key']) {
            throw new RuntimeException(__('packages.form_key_differs', ['form' => $this->name($form)]));
        }
        $doc = $c['document'];
        $doc['form']['key'] = $form->key;
        $doc['form']['application'] = strtolower($application->uuid);
        $doc['form']['bindingMode'] = 'managed';
        foreach (['numbering' => 'number_sequences', 'calendar' => 'business_calendars'] as $prop => $table) {
            if (isset($doc['form'][$prop]) && ! DB::table($table)->where('uuid', $doc['form'][$prop])->exists()) {
                $doc['form'][$prop] = null;
                $notes[] = __("packages.dropped_{$prop}", ['form' => $this->name($form)]);
            }
        }
        $live = $form->current_version_id === null ? null : FormVersion::query()->find($form->current_version_id);
        try {
            $this->publish($form, $doc, (array) $c['workflow'], $user, $options);
        } catch (Throwable $e) {
            // A form that is not imported stays exactly as it was: its draft goes back to what is published.
            if ($live !== null) {
                $this->forms->loadVersionIntoDraft($form->refresh(), $live);
            }

            throw $e;
        }
        $form->refresh();
        $this->notifications->save($form, array_values((array) $c['notifications']), $user->id);

        return [...$notes, ...$this->applyAccess($form, (array) ($c['access'] ?? []))];
    }

    /**
     * Saves the draft and workflow, then publishes through the normal migration plan.
     *
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>  $workflow
     * @param  array<string, mixed>  $options
     */
    private function publish(Form $form, array $doc, array $workflow, User $user, array $options): void
    {
        try {
            $this->drafts->save($form, $doc, null, $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id));
        } catch (DraftInvalid $e) {
            throw new RuntimeException(__('packages.form_invalid', ['form' => $this->name($form), 'detail' => $e->errors[0]['message'] ?? '']));
        }
        $this->workflow->save($form->refresh(), $workflow, $user->id);
        $form->refresh();
        $prepared = $this->publisher->prepare($form);
        try {
            $plan = $this->publisher->start($form, $user->id, [
                'impact_hash' => $prepared['hash'], 'purpose' => 'publish', 'change_note' => __('packages.change_note'),
                'confirm_blocking' => (bool) ($options['confirm_blocking'] ?? false), 'confirm_destructive' => (bool) ($options['confirm_destructive'] ?? false),
                'typed_confirmation' => ($options['confirm_destructive'] ?? false) ? $form->key : null,
            ]);
        } catch (PublishBlocked $e) {
            throw new RuntimeException(__('packages.publish_blocked', ['form' => $this->name($form), 'reason' => $e->getMessage()]));
        }
        if ($this->publisher->execute($plan) !== 'applied' || $plan->refresh()->status !== 'applied') {
            throw new RuntimeException(__('packages.publish_failed', ['form' => $this->name($form)]));
        }
    }

    private function name(Form $form): string
    {
        return $this->translator->labelOf($form->translationsFor('name'), (string) $form->key);
    }

    private function unpublished(Form $form): bool
    {
        if ($form->current_version_id === null) {
            return true;
        }
        $published = $this->definitions->version($form->id, (int) $form->current_version_id);
        $draft = app(DefinitionCompiler::class)->compile($form, (int) $published['form']['version'])['definition'];
        $strip = static fn (array $d): array => array_diff_key($d, array_flip(['access', 'targets', 'schema'])) + ['workflow' => ['statuses' => [], 'transitions' => [], 'sla' => []]];

        return DefinitionCompiler::hash($strip($draft)) !== DefinitionCompiler::hash($strip($published));
    }

    /** @return array{rules: list<array<string, mixed>>, grants: list<array<string, mixed>>} */
    private function access(Form $form): array
    {
        $groups = DB::table('field_groups')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $fields = DB::table('fields')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $statuses = DB::table('statuses')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $rules = [];
        foreach (DB::table('field_access_rules')->where('form_id', $form->id)->orderBy('id')->get() as $r) {
            $rules[] = [
                'target' => $r->target_type, 'group' => self::uuid($groups, $r->group_id), 'field' => self::uuid($fields, $r->field_id), 'status' => self::uuid($statuses, $r->status_id),
                'subjectType' => $r->subject_type, 'subject' => $this->subject((string) $r->subject_type, $r->subject_id === null ? null : (int) $r->subject_id),
                'mode' => $r->mode, 'access' => $r->access, 'effect' => $r->effect,
            ];
        }
        $grants = [];
        foreach (DB::table('permission_assignments')->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->where('permissions.key', 'like', "form.{$form->uuid}.%")->orderBy('permission_assignments.id')
            ->get(['permissions.key', 'subject_type', 'subject_id', 'effect', 'include_descendants']) as $a) {
            $grants[] = [
                'ability' => substr((string) $a->key, strlen("form.{$form->uuid}.")), 'subjectType' => $a->subject_type,
                'subject' => $this->subject((string) $a->subject_type, (int) $a->subject_id), 'effect' => $a->effect, 'includeDescendants' => (bool) $a->include_descendants,
            ];
        }

        return ['rules' => $rules, 'grants' => $grants];
    }

    /**
     * @param  array{rules?: list<array<string, mixed>>, grants?: list<array<string, mixed>>}  $access
     * @return list<string>
     */
    private function applyAccess(Form $form, array $access): array
    {
        $ids = static fn (string $table) => DB::table($table)->where('form_id', $form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        [$groups, $fields, $statuses] = [$ids('field_groups'), $ids('fields'), $ids('statuses')];
        $skipped = [];
        foreach ($access['rules'] ?? [] as $r) {
            $subject = $r['subjectType'] === 'everyone' ? null : $this->subjectId((string) $r['subjectType'], $r['subject'] ?? null);
            if ($r['subjectType'] !== 'everyone' && $subject === null) {
                $skipped[] = __('packages.skipped_subject', ['form' => $this->name($form), 'what' => __('packages.subject.'.$r['subjectType'])]);

                continue;
            }
            $this->rules->put($form, (string) $r['target'], $groups[$r['group'] ?? ''] ?? null, $fields[$r['field'] ?? ''] ?? null, (string) $r['subjectType'], $subject,
                $r['mode'] ?? null, $r['access'] ?? null, $r['effect'] ?? null, $statuses[$r['status'] ?? ''] ?? null);
        }
        foreach ($access['grants'] ?? [] as $g) {
            $subject = $this->subjectId((string) $g['subjectType'], $g['subject'] ?? null);
            $permission = DB::table('permissions')->where('key', "form.{$form->uuid}.{$g['ability']}")->value('id');
            if ($subject === null || $permission === null) {
                $skipped[] = __('packages.skipped_subject', ['form' => $this->name($form), 'what' => __('packages.subject.'.$g['subjectType'])]);

                continue;
            }
            PermissionAssignment::query()->updateOrCreate(['permission_id' => $permission, 'subject_type' => $g['subjectType'], 'subject_id' => $subject],
                ['effect' => $g['effect'], 'include_descendants' => (bool) ($g['includeDescendants'] ?? false)]);
        }
        $this->cache->bump();

        return $skipped;
    }

    /** @param  array<int|string, mixed>  $map */
    private static function uuid(array $map, mixed $id): ?string
    {
        return $id === null || ! isset($map[$id]) ? null : strtolower((string) $map[$id]);
    }

    private function subject(string $type, ?int $id): ?string
    {
        $table = ['user' => 'users', 'role' => 'roles', 'department' => 'departments'][$type] ?? null;

        return $table === null || $id === null ? null : strtolower((string) DB::table($table)->where('id', $id)->value('uuid'));
    }

    private function subjectId(string $type, ?string $uuid): ?int
    {
        $table = ['user' => 'users', 'role' => 'roles', 'department' => 'departments'][$type] ?? null;
        $id = $table === null || $uuid === null ? null : DB::table($table)->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }
}
