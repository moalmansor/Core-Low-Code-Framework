<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Core\I18n\Translator;
use App\Modules\Documents\DocumentTemplates;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\PackagePart;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Document templates: designed ones, and Word ones with their files (carried in the package). */
final class DocumentTemplatePart implements PackagePart
{
    public function __construct(private readonly DocumentTemplates $templates, private readonly Translator $translator) {}

    public function type(): string
    {
        return 'document_template';
    }

    public function catalog(): array
    {
        return DocumentTemplate::query()->orderBy('key')->get()
            ->map(fn (DocumentTemplate $t) => ['uuid' => strtolower($t->uuid), 'key' => (string) $t->key, 'label' => $this->translator->labelOf($t->translationsFor('name'), (string) $t->key)])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $t = DocumentTemplate::query()->where('uuid', $uuid)->first();
        if ($t === null) {
            return null;
        }
        $p = $this->templates->present($t);
        $files = [];
        foreach ((array) $t->locale_files as $locale => $fileUuid) {
            $f = StoredFile::query()->where('uuid', $fileUuid)->first();
            if ($f !== null) {
                $files[$locale] = ['name' => $f->original_name, 'data' => base64_encode((string) Storage::disk($f->disk)->get($f->path))];
            }
        }

        return ['uuid' => $p['uuid'], 'key' => (string) $t->key, 'label' => $this->translator->labelOf($t->translationsFor('name'), (string) $t->key),
            'dependencies' => array_values(array_filter([$p['form']])),
            'content' => ['type' => $p['type'], 'form' => $p['form'], 'name' => (array) $p['name'], 'design' => $p['design'], 'files' => $files, 'formats' => $p['formats'], 'paper' => $p['paper'], 'active' => $p['active']]];
    }

    public function exists(string $uuid): bool
    {
        return DocumentTemplate::query()->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return DocumentTemplate::query()->where('key', $key)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $c = $object['content'];
        $t = DocumentTemplate::query()->where('uuid', $object['uuid'])->first();
        if ($t === null) {
            $t = new DocumentTemplate;
            $t->uuid = $object['uuid'];
        }
        $files = [];
        foreach ((array) ($c['files'] ?? []) as $locale => $f) {
            // Word files are uploaded again, through the same checks as a file chosen on the screen.
            $tmp = tempnam(sys_get_temp_dir(), 'lcfpkg');
            file_put_contents((string) $tmp, (string) base64_decode((string) ($f['data'] ?? ''), true));
            $files[$locale] = $this->templates->upload(new UploadedFile((string) $tmp, (string) ($f['name'] ?? 'template.docx'), null, null, true), $c['form'] ?? null)['uuid'];
        }
        $this->templates->save($t, ['key' => $object['key'], 'type' => $c['type'], 'form' => $c['form'] ?? null, 'name' => $c['name'] ?? [], 'design' => $c['design'] ?? null,
            'files' => $files, 'formats' => $c['formats'] ?? [], 'paper' => $c['paper'] ?? [], 'active' => $c['active'] ?? true], $user->id);

        return [];
    }
}
