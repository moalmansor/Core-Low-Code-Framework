<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Documents\Docx\DocxPackage;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Templating\BlockDesign;
use App\Modules\Records\Templating\TemplateValues;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Document templates (specification §4.17): a Word file with placeholders
 * per language, or a design of blocks; either produces DOCX and, where
 * chosen, PDF. A template tied to a form may use its fields, relation paths
 * and repeater columns; every placeholder is checked when the template is
 * saved, and again when it is shown, so a field removed from the form later
 * is reported rather than silently printing nothing.
 */
final class DocumentTemplates
{
    public const BLOCKS = ['heading', 'text', 'fields', 'table', 'divider', 'spacer', 'page_break'];

    public const PAPER_SIZES = ['a4', 'a3', 'a5', 'letter', 'legal'];

    public const DEFAULT_PAPER = ['size' => 'a4', 'orientation' => 'portrait', 'margins' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20]];

    public function __construct(
        private readonly Translator $translator,
        private readonly FormRuntimes $runtimes,
        private readonly BlockDesign $design,
        private readonly TemplateValues $values,
        private readonly FileStore $files,
        private readonly PdfConverter $converter,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array<string, mixed> */
    public function present(DocumentTemplate $t): array
    {
        $form = $t->form_id === null ? null : Form::query()->find($t->form_id);
        $files = [];
        foreach ((array) $t->locale_files as $locale => $uuid) {
            $f = StoredFile::query()->where('uuid', $uuid)->first();
            $files[$locale] = $f === null ? null : ['uuid' => strtolower($f->uuid), 'name' => $f->original_name, 'size' => $f->size_bytes];
        }

        return [
            'uuid' => strtolower($t->uuid),
            'key' => $t->key,
            'type' => $t->type,
            'form' => $form === null ? null : strtolower($form->uuid),
            'name' => (object) $t->translationsFor('name'),
            'design' => $t->design,
            'files' => (object) $files,
            'formats' => $t->output_formats,
            'paper' => $t->paper,
            'placeholders' => $t->placeholders_detected,
            'issues' => $this->issues($t, $form === null ? null : $this->runtimes->forForm($form)),
            'active' => $t->is_active,
            'hash' => hash('sha256', (string) json_encode([$t->key, $t->type, $t->form_id, $t->translationsFor('name'), $t->design, $t->locale_files, $t->output_formats, $t->paper, $t->is_active])),
        ];
    }

    /**
     * Placeholders of a saved template that no longer name anything (a field
     * was removed or renamed since): text for the designer to show.
     *
     * @return list<string>
     */
    public function issues(DocumentTemplate $t, ?FormRuntime $rt): array
    {
        $out = [];
        foreach ($t->placeholders_detected as $token) {
            if (! $this->tokenOk($token, $rt)) {
                $out[] = __('documents.placeholder_gone', ['placeholder' => $token]);
            }
        }

        return $out;
    }

    /**
     * Checks and stores an uploaded Word template, not yet attached to a
     * template (it is claimed when the template is saved).
     *
     * @return array{uuid: string, name: string, size: int, placeholders: list<string>, unknown: list<string>}
     */
    public function upload(UploadedFile $upload, ?string $formUuid): array
    {
        $file = $this->files->storeUpload($upload, ['docx'], 1024 * (int) $this->settings->get('documents', 'max_template_mb'), exact: true);
        $found = $this->inspect($file);
        if ($found['problems'] !== []) {
            $file->forceFill(['deleted_at' => now()])->save();
            throw ValidationException::withMessages(['file' => array_map(static fn (string $p) => __('documents.problem.'.$p), $found['problems'])]);
        }
        $form = $formUuid === null ? null : Form::query()->where('uuid', strtolower($formUuid))->first();
        $rt = $form === null ? null : $this->runtimes->forForm($form);

        return [
            'uuid' => strtolower($file->uuid), 'name' => $file->original_name, 'size' => $file->size_bytes,
            'placeholders' => $found['tokens'],
            'unknown' => array_values(array_filter($found['tokens'], fn (string $t) => ! $this->tokenOk($t, $rt))),
        ];
    }

    /**
     * Creates or replaces a template.
     *
     * @param  array<string, mixed>  $data  {key, type, form, name, design, files, formats, paper, active}
     */
    public function save(?DocumentTemplate $t, array $data, int $userId): DocumentTemplate
    {
        $errors = [];
        $form = isset($data['form']) ? Form::query()->where('uuid', strtolower((string) $data['form']))->first() : null;
        if (isset($data['form']) && $form === null) {
            $errors['form'][] = __('documents.unknown_form');
        }
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $type = (string) ($data['type'] ?? '');
        if ($t !== null && $type !== $t->type) {
            $errors['type'][] = __('documents.type_fixed');
        }
        if (DocumentTemplate::query()->where('key', $data['key'])->when($t !== null, fn ($q) => $q->where('id', '!=', $t->id))->exists()) {
            $errors['key'][] = __('documents.key_taken');
        }
        $name = $this->design->i18n($data['name'] ?? [], 'name', null, $errors, 255);
        $formats = array_values(array_intersect(['docx', 'pdf'], (array) ($data['formats'] ?? [])));
        if ($formats === []) {
            $errors['formats'][] = __('documents.formats_required');
        }
        if ($type === 'docx' && in_array('pdf', $formats, true) && ! $this->converter->available()) {
            $errors['formats'][] = __('documents.converter_missing');
        }
        $paper = $this->paper((array) ($data['paper'] ?? []), $errors);
        $design = null;
        $files = [];
        $tokens = [];
        if ($type === 'html') {
            $style = (array) ($data['design']['style'] ?? []);
            $accent = $this->design->colour($style['accent'] ?? null, 'design.style.accent', $errors);
            $design = ['style' => ['accent' => $accent], 'blocks' => $this->design->blocks($form, $rt, $data['design']['blocks'] ?? [], self::BLOCKS, 'design.blocks', $errors)];
            if ($design['blocks'] === [] && ! isset($errors['design.blocks'])) {
                $errors['design.blocks'][] = __('documents.blocks_required');
            }
            foreach ($design['blocks'] as $b) {
                foreach ((array) ($b['text'] ?? []) as $text) {
                    $tokens = [...$tokens, ...TemplateValues::tokens((string) $text)];
                }
                $tokens = [...$tokens, ...array_map('strval', (array) ($b['fields'] ?? []))];
                foreach ((array) ($b['columns'] ?? []) as $c) {
                    $tokens[] = ($b['group'] ?? '').'.'.$c;
                }
            }
        } elseif ($type === 'docx') {
            [$files, $tokens] = $this->wordFiles((array) ($data['files'] ?? []), $t, $rt, $userId, $errors);
        } else {
            $errors['type'][] = __('validation.in', ['attribute' => 'type']);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($t, $data, $type, $form, $name, $design, $files, $tokens, $formats, $paper, $userId): DocumentTemplate {
            $t ??= new DocumentTemplate;
            $previous = array_values((array) $t->locale_files);
            $t->fill([
                'key' => $data['key'], 'type' => $type, 'form_id' => $form?->id, 'design' => $design,
                'locale_files' => $type === 'docx' ? array_map(static fn (StoredFile $f) => strtolower($f->uuid), $files) : null,
                'file_id' => $type === 'docx' ? $files[$this->translator->defaultLocale()]->id : null,
                'output_formats' => $formats, 'paper' => $paper, 'placeholders_detected' => array_values(array_unique($tokens)), 'is_active' => (bool) ($data['active'] ?? true),
            ]);
            $t->save();
            foreach ($files as $f) {
                $f->forceFill(['owner_type' => 'document_template', 'owner_id' => $t->id, 'is_temporary' => false])->save();
            }
            // Word files this template no longer uses are removed.
            $kept = array_map(static fn (StoredFile $f) => strtolower($f->uuid), $files);
            StoredFile::query()->whereIn('uuid', array_diff($previous, $kept))->where('owner_type', 'document_template')->update(['deleted_at' => now()]);
            $this->translator->putMany('document_template', $t->id, 'name', $this->design->locales($name));
            $html = [];
            foreach ($this->translator->enabledLocales() as $locale) {
                $html[$locale] = $design === null ? null : $this->compile($design, $locale);
            }
            $this->translator->putMany('document_template', $t->id, 'html', $html);
            $this->audit->record('documents.template_saved', 'config', null, 'document_template', $t->id, ['key' => $t->key, 'type' => $t->type], $userId);

            return $t->refresh();
        });
    }

    public function delete(DocumentTemplate $t, int $userId): void
    {
        if ($this->usedBy($t) > 0) {
            throw ValidationException::withMessages(['template' => __('documents.in_use')]);
        }
        DB::transaction(function () use ($t, $userId): void {
            StoredFile::query()->where('owner_type', 'document_template')->where('owner_id', $t->id)->update(['deleted_at' => now()]);
            $this->translator->forget('document_template', $t->id);
            $this->audit->record('documents.template_deleted', 'config', null, 'document_template', $t->id, ['key' => $t->key], $userId);
            $t->delete();
        });
    }

    /** How many notification rules attach this template. */
    public function usedBy(DocumentTemplate $t): int
    {
        return DB::table('notification_rules')->whereNotNull('attachments')->where('attachments', 'like', '%'.strtolower($t->uuid).'%')->count();
    }

    /** A placeholder a template of this form may use: a system value, a field or relation path, or a repeater column. */
    public function tokenOk(string $token, ?FormRuntime $rt): bool
    {
        if ($this->values->pathOk($token, $rt)) {
            return true;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $rt === null) {
            return false;
        }
        $rep = $this->values->repeater($rt, $parts[0]);

        return $rep !== null && in_array($parts[1], array_map(static fn ($f) => $f['key'], $rt->rowFields($rep)), true);
    }

    /**
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}
     */
    private function paper(array $paper, array &$errors): array
    {
        $size = (string) ($paper['size'] ?? 'a4');
        if (! in_array($size, self::PAPER_SIZES, true)) {
            $errors['paper.size'][] = __('validation.in', ['attribute' => 'size']);
        }
        $orientation = ($paper['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $margins = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $v = $paper['margins'][$side] ?? 20;
            if (! is_numeric($v) || (int) $v < 0 || (int) $v > 60) {
                $errors["paper.margins.{$side}"][] = __('documents.bad_margin');
            }
            $margins[$side] = (int) $v;
        }

        return ['size' => $size, 'orientation' => $orientation, 'margins' => $margins];
    }

    /**
     * The Word file of each language: uploaded by this user and not yet
     * claimed, or already this template's; each checked again.
     *
     * @param  array<string, mixed>  $map  locale => file uuid
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array{0: array<string, StoredFile>, 1: list<string>}
     */
    private function wordFiles(array $map, ?DocumentTemplate $t, ?FormRuntime $rt, int $userId, array &$errors): array
    {
        $files = [];
        $tokens = [];
        $default = $this->translator->defaultLocale();
        $ownerId = $t === null ? 0 : $t->id;
        foreach ($map as $locale => $uuid) {
            if (! in_array($locale, $this->translator->enabledLocales(), true) || ! is_string($uuid) || $uuid === '') {
                continue;
            }
            $f = StoredFile::query()->where('uuid', strtolower($uuid))->whereNull('deleted_at')->where('extension', 'docx')
                ->where(fn ($q) => $q->where(fn ($w) => $w->where('is_temporary', true)->where('uploaded_by', $userId))
                    ->orWhere(fn ($w) => $w->where('owner_type', 'document_template')->where('owner_id', $ownerId)))
                ->first();
            if ($f === null) {
                $errors["files.{$locale}"][] = __('documents.file_missing');

                continue;
            }
            $found = $this->inspect($f);
            foreach ($found['problems'] as $p) {
                $errors["files.{$locale}"][] = __('documents.problem.'.$p);
            }
            foreach ($found['tokens'] as $token) {
                if (! $this->tokenOk($token, $rt)) {
                    $errors["files.{$locale}"][] = __('templates.unknown_placeholder', ['placeholder' => $token]);
                }
            }
            $files[$locale] = $f;
            $tokens = [...$tokens, ...$found['tokens']];
        }
        if (! isset($files[$default]) && ! isset($errors["files.{$default}"])) {
            $errors["files.{$default}"][] = __('documents.file_required');
        }

        return [$files, $tokens];
    }

    /** @return array{tokens: list<string>, row_groups: list<string>, problems: list<string>} */
    private function inspect(StoredFile $file): array
    {
        $path = tempnam(sys_get_temp_dir(), 'lcf-tpl-');
        if ($path === false) {
            return ['tokens' => [], 'row_groups' => [], 'problems' => ['not_docx']];
        }
        try {
            file_put_contents($path, (string) Storage::disk($file->disk)->get($file->path));

            return DocxPackage::inspect($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * The design as HTML in one language with its placeholders as written
     * (stored as the template's `html` translation, for reading and search).
     *
     * @param  array<string, mixed>  $design
     */
    private function compile(array $design, string $locale): string
    {
        $parts = [];
        foreach ((array) ($design['blocks'] ?? []) as $b) {
            $text = e((string) ($this->translator->pick((array) ($b['text'] ?? []), $locale) ?? ''));
            $parts[] = match ($b['type'] ?? '') {
                'heading' => '<h1>'.$text.'</h1>',
                'text' => '<p>'.nl2br($text).'</p>',
                'fields' => '<ul>'.implode('', array_map(static fn ($p) => '<li>{{ '.e((string) $p).' }}</li>', (array) ($b['fields'] ?? []))).'</ul>',
                'table' => '<table><tr>'.implode('', array_map(static fn ($c) => '<td>{{ '.e((string) ($b['group'] ?? '')).'.'.e((string) $c).' }}</td>', (array) ($b['columns'] ?? []))).'</tr></table>',
                'divider' => '<hr>',
                'page_break' => '<hr class="page-break">',
                default => '',
            };
        }

        return implode("\n", array_filter($parts));
    }
}
