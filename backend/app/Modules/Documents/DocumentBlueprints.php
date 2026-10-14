<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Modules\Audit\AuditWriter;
use App\Modules\Blueprints\BlueprintService;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\BlueprintInstance;
use App\Modules\Blueprints\Models\BlueprintVersion;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Blueprints of document templates (specification §4.17, §4.30): a template
 * saved as a blueprint holds its name, kind, design or Word files (carried in
 * the blueprint itself, so an exported blueprint is complete), paper and
 * formats. A template is created from it for any form; its placeholders are
 * checked against that form as on any save. New versions propagate per
 * property with the three-way merge of views: what the template changed
 * locally is kept and listed as a conflict (ADR-0046).
 */
final class DocumentBlueprints
{
    public const KIND = 'document_template';

    private const PROPS = ['name', 'design', 'paper', 'formats', 'files'];

    public function __construct(
        private readonly DocumentTemplates $templates,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
    ) {}

    /** @param  array{name: array<string, string>, description?: array<string, string>, category?: string|null, tags?: list<string>, is_library?: bool}  $meta */
    public function create(string $templateUuid, array $meta): Blueprint
    {
        $t = $this->template($templateUuid);

        return DB::transaction(function () use ($t, $meta): Blueprint {
            $bp = new Blueprint([
                'kind' => self::KIND, 'category' => $meta['category'] ?? null, 'tags' => $meta['tags'] ?? [],
                'is_library' => $meta['is_library'] ?? false, 'source_type' => self::KIND, 'source_id' => $t->id,
            ]);
            $bp->organization_id = $this->tenant->organizationId();
            $bp->save();
            $bp->setTranslations('name', $meta['name']);
            if (isset($meta['description'])) {
                $bp->setTranslations('description', $meta['description']);
            }
            $this->addVersion($bp, $t->uuid, null);

            return $bp->refresh();
        });
    }

    public function addVersion(Blueprint $bp, ?string $templateUuid, ?string $changelog): BlueprintVersion
    {
        $templateUuid ??= DocumentTemplate::query()->whereKey($bp->source_id)->value('uuid');
        if ($templateUuid === null || $bp->kind !== self::KIND) {
            throw ValidationException::withMessages(['source' => __('blueprints.source_missing')]);
        }
        $content = $this->capture($this->template((string) $templateUuid));
        $hash = BlueprintService::hash($content);
        $current = $bp->current_version_id === null ? null : BlueprintVersion::query()->find($bp->current_version_id);
        if ($current !== null && hash_equals($current->content_hash, $hash)) {
            throw ValidationException::withMessages(['source' => __('blueprints.no_changes')]);
        }

        return DB::transaction(function () use ($bp, $content, $hash, $changelog): BlueprintVersion {
            $number = (int) BlueprintVersion::query()->where('blueprint_id', $bp->id)->max('version') + 1;
            $version = BlueprintVersion::query()->create(['blueprint_id' => $bp->id, 'version' => $number, 'content' => $content, 'content_hash' => $hash, 'include_mode' => 'structure', 'changelog' => $changelog]);
            $bp->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('blueprint.version_created', 'config', null, 'blueprint', $bp->id, ['version' => $number, 'kind' => self::KIND]);

            return $version;
        });
    }

    /**
     * A new template from a blueprint version, for a form (or for no form).
     *
     * @param  array<string, string>|null  $name  the blueprint's name when null
     */
    public function instantiate(Blueprint $bp, BlueprintVersion $version, ?Form $form, string $key, ?array $name): DocumentTemplate
    {
        $content = (array) $version->content['template'];
        $userId = (int) Auth::id();

        return DB::transaction(function () use ($bp, $version, $content, $form, $key, $name, $userId): DocumentTemplate {
            $t = $this->templates->save(null, [
                'key' => $key, 'type' => $content['type'], 'form' => $form?->uuid, 'name' => $name ?? $content['name'],
                'design' => $content['design'] ?? null, 'files' => $this->files((array) ($content['files'] ?? []), $form), 'formats' => $content['formats'],
                'paper' => $content['paper'] ?? DocumentTemplates::DEFAULT_PAPER, 'active' => true,
            ], $userId);
            BlueprintInstance::query()->create(['blueprint_id' => $bp->id, 'blueprint_version_id' => $version->id, 'object_type' => self::KIND, 'object_id' => $t->id, 'include_mode' => 'structure', 'is_detached' => false]);
            $this->audit->record('blueprint.instantiated', 'config', null, 'blueprint', $bp->id, ['version' => $version->version, 'document_template' => $t->uuid]);

            return $t;
        });
    }

    /** @return list<array<string, mixed>> what each template made from it would receive */
    public function preview(Blueprint $bp): array
    {
        $out = [];
        foreach ($this->outdated($bp) as [$instance, $t]) {
            $out[] = [
                'instance' => $instance->uuid,
                'template' => ['uuid' => strtolower($t->uuid), 'key' => $t->key, 'name' => $t->translate('name')],
                'from_version' => (int) BlueprintVersion::query()->whereKey($instance->baseVersionId())->value('version'),
                'changes' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $this->plan($bp, $instance, $t)['changes']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $instanceUuids
     * @return list<array<string, mixed>>
     */
    public function propagate(Blueprint $bp, ?array $instanceUuids): array
    {
        $results = [];
        foreach ($this->outdated($bp) as [$instance, $t]) {
            if ($instanceUuids !== null && ! in_array($instance->uuid, $instanceUuids, true)) {
                continue;
            }
            $plan = $this->plan($bp, $instance, $t);
            $conflicts = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'conflict'));
            $applied = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'apply'));
            try {
                if ($applied !== []) {
                    $form = $t->form_id === null ? null : Form::query()->find($t->form_id);
                    $merged = $plan['template'];
                    $this->templates->save($t, [
                        'key' => $t->key, 'type' => $t->type, 'form' => $form?->uuid, 'name' => $merged['name'], 'design' => $merged['design'],
                        'files' => $merged['files'] === $this->capture($t)['template']['files'] ? array_map(static fn ($f) => $f['uuid'] ?? null, (array) $this->templates->present($t)['files']) : $this->files((array) $merged['files'], $form),
                        'formats' => $merged['formats'], 'paper' => $merged['paper'], 'active' => $t->is_active,
                    ], (int) Auth::id());
                }
                $instance->forceFill(['last_propagated_version_id' => $bp->current_version_id])->save();
                $this->audit->record('blueprint.propagated', 'config', null, 'document_template', $t->id, ['blueprint' => $bp->uuid, 'applied' => count($applied), 'conflicts' => count($conflicts)]);
                $results[] = ['instance' => $instance->uuid, 'template' => strtolower($t->uuid), 'status' => 'applied', 'applied' => count($applied), 'conflicts' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $conflicts)];
            } catch (ValidationException $e) {
                $results[] = ['instance' => $instance->uuid, 'template' => strtolower($t->uuid), 'status' => 'failed', 'errors' => $e->errors()];
            }
        }

        return $results;
    }

    /**
     * Whether an imported version's content is a document template.
     *
     * @param  array<string, mixed>  $content
     */
    public static function valid(array $content): bool
    {
        $t = $content['template'] ?? null;
        if (! is_array($t) || ! in_array($t['type'] ?? null, ['html', 'docx'], true) || ! is_array($t['name'] ?? null) || ! is_array($t['formats'] ?? null)) {
            return false;
        }
        if ($t['type'] === 'html') {
            return is_array($t['design'] ?? null) && is_array($t['design']['blocks'] ?? null);
        }
        foreach ((array) ($t['files'] ?? []) as $file) {
            if (! is_array($file) || ! is_string($file['data'] ?? null) || base64_decode($file['data'], true) === false) {
                return false;
            }
        }

        return ($t['files'] ?? []) !== [];
    }

    /** @return array<string, mixed> */
    public function capture(DocumentTemplate $t): array
    {
        $files = [];
        foreach ((array) $t->locale_files as $locale => $uuid) {
            $f = StoredFile::query()->where('uuid', $uuid)->whereNull('deleted_at')->first();
            if ($f !== null) {
                $files[$locale] = ['name' => $f->original_name, 'data' => base64_encode((string) Storage::disk($f->disk)->get($f->path))];
            }
        }
        ksort($files);

        return [
            'format' => BlueprintService::FORMAT, 'kind' => self::KIND, 'source' => strtolower($t->uuid), 'suggestedKey' => $t->key,
            'sourceForm' => $t->form_id === null ? null : strtolower((string) DB::table('forms')->where('id', $t->form_id)->value('uuid')),
            'template' => [
                'type' => $t->type, 'name' => $t->translationsFor('name'), 'design' => $t->design, 'paper' => $t->paper,
                'formats' => $t->output_formats, 'files' => $files,
            ],
        ];
    }

    /**
     * Word files of a blueprint, uploaded again as this user's so the
     * template's save checks and claims them like any upload.
     *
     * @param  array<string, mixed>  $files  locale => {name, data}
     * @return array<string, string> locale => uploaded file uuid
     */
    private function files(array $files, ?Form $form): array
    {
        $out = [];
        foreach ($files as $locale => $file) {
            $path = tempnam(sys_get_temp_dir(), 'lcf-bp-');
            if ($path === false || ! is_array($file)) {
                continue;
            }
            file_put_contents($path, (string) base64_decode((string) ($file['data'] ?? ''), true));
            $name = is_string($file['name'] ?? null) && $file['name'] !== '' ? $file['name'] : 'template.docx';
            $out[(string) $locale] = $this->templates->upload(new UploadedFile($path, $name, DocumentRenderer::MIME['docx'], null, true), $form?->uuid)['uuid'];
        }

        return $out;
    }

    private function template(string $uuid): DocumentTemplate
    {
        $t = DocumentTemplate::query()->where('uuid', strtolower($uuid))->first();
        if ($t === null) {
            throw ValidationException::withMessages(['source' => __('blueprints.source_missing')]);
        }

        return $t;
    }

    /** @return list<array{0: BlueprintInstance, 1: DocumentTemplate}> */
    private function outdated(Blueprint $bp): array
    {
        $out = [];
        foreach (BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('is_detached', false)->where('object_type', self::KIND)->orderBy('id')->get() as $instance) {
            $t = $instance->baseVersionId() === $bp->current_version_id ? null : DocumentTemplate::query()->find($instance->object_id);
            if ($t !== null) {
                $out[] = [$instance, $t];
            }
        }

        return $out;
    }

    /**
     * Three-way merge per property: blueprint base → current, onto the template.
     *
     * @return array{template: array<string, mixed>, changes: list<array<string, mixed>>}
     */
    private function plan(Blueprint $bp, BlueprintInstance $instance, DocumentTemplate $t): array
    {
        $base = (array) BlueprintVersion::query()->findOrFail($instance->baseVersionId())->content['template'];
        $target = (array) BlueprintVersion::query()->findOrFail($bp->current_version_id)->content['template'];
        $local = $this->capture($t)['template'];
        $merged = $local;
        $changes = [];
        foreach (self::PROPS as $prop) {
            if (self::same($base[$prop] ?? null, $target[$prop] ?? null)) {
                continue;
            }
            $status = self::same($local[$prop] ?? null, $base[$prop] ?? null) ? 'apply' : (self::same($local[$prop] ?? null, $target[$prop] ?? null) ? 'skip' : 'conflict');
            $changes[] = ['kind' => 'document_template', 'uuid' => null, 'label' => $prop, 'action' => 'update', 'status' => $status, 'reason' => $status === 'conflict' ? 'modified_locally' : null, 'value' => $target[$prop] ?? null];
            if ($status === 'apply') {
                $merged[$prop] = $target[$prop] ?? null;
            }
        }

        return ['template' => $merged, 'changes' => $changes];
    }

    private static function same(mixed $a, mixed $b): bool
    {
        $canon = static function (mixed $v) use (&$canon): mixed {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($canon, $v);
        };

        return json_encode($canon($a)) === json_encode($canon($b));
    }
}
