<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\EmailTemplates;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Platform\Packages\PackagePart;

/** Email templates: design, subject and preheader per language, the form or application they belong to. */
final class EmailTemplatePart implements PackagePart
{
    public function __construct(private readonly EmailTemplates $templates, private readonly Translator $translator) {}

    public function type(): string
    {
        return 'email_template';
    }

    public function catalog(): array
    {
        return EmailTemplate::query()->orderBy('key')->get()
            ->map(fn (EmailTemplate $t) => ['uuid' => strtolower($t->uuid), 'key' => (string) $t->key, 'label' => $this->translator->labelOf($t->translationsFor('subject'), (string) $t->key)])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $t = EmailTemplate::query()->where('uuid', $uuid)->first();
        if ($t === null) {
            return null;
        }
        $p = $this->templates->present($t);

        return ['uuid' => $p['uuid'], 'key' => (string) $t->key, 'label' => $this->translator->labelOf($t->translationsFor('subject'), (string) $t->key),
            'dependencies' => array_values(array_filter([$p['form'], $p['application']])),
            'content' => ['form' => $p['form'], 'application' => $p['application'], 'design' => $p['design'], 'subject' => (array) $p['subject'], 'preheader' => (array) $p['preheader'], 'active' => $p['active']]];
    }

    public function exists(string $uuid): bool
    {
        return EmailTemplate::query()->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return EmailTemplate::query()->where('key', $key)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $t = EmailTemplate::query()->where('uuid', $object['uuid'])->first();
        if ($t === null) {
            $t = new EmailTemplate;
            $t->uuid = $object['uuid'];
        }
        $this->templates->save($t, ['key' => $object['key']] + $object['content'], $user->id);

        return [];
    }
}
