<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Templating\BlockDesign;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Email templates (specification §4.16): a design of blocks (heading, text,
 * button, field list, repeater table, divider, spacer), each optionally shown
 * only when a condition holds, plus a subject and preheader per language. A
 * template tied to a form may use that form's fields and relation paths as
 * placeholders; one without a form only system values.
 */
final class EmailTemplates
{
    public const BLOCKS = ['heading', 'text', 'button', 'fields', 'table', 'divider', 'spacer'];

    public function __construct(
        private readonly Translator $translator,
        private readonly FormRuntimes $runtimes,
        private readonly BlockDesign $design,
        private readonly TemplateRenderer $renderer,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array<string, mixed> */
    public function present(EmailTemplate $t): array
    {
        return [
            'uuid' => strtolower($t->uuid),
            'key' => $t->key,
            'form' => $t->form_id === null ? null : strtolower((string) DB::table('forms')->where('id', $t->form_id)->value('uuid')),
            'application' => $t->application_id === null ? null : strtolower((string) DB::table('applications')->where('id', $t->application_id)->value('uuid')),
            'design' => $t->design,
            'subject' => (object) $t->translationsFor('subject'),
            'preheader' => (object) $t->translationsFor('preheader'),
            'active' => $t->is_active,
            'hash' => hash('sha256', (string) json_encode([$t->design, $t->translationsFor('subject'), $t->translationsFor('preheader'), $t->is_active, $t->key, $t->form_id, $t->application_id])),
        ];
    }

    /**
     * Creates or replaces a template.
     *
     * @param  array<string, mixed>  $data  {key, form, application, design, subject, preheader, active}
     */
    public function save(?EmailTemplate $t, array $data, int $userId): EmailTemplate
    {
        $form = isset($data['form']) ? Form::query()->where('uuid', strtolower((string) $data['form']))->first() : null;
        if (isset($data['form']) && $form === null) {
            throw ValidationException::withMessages(['form' => __('notifications.unknown_form')]);
        }
        $app = isset($data['application']) ? Application::query()->where('uuid', strtolower((string) $data['application']))->first() : null;
        $design = $this->validate($form, (array) ($data['design'] ?? []), (array) ($data['subject'] ?? []), (array) ($data['preheader'] ?? []));
        $exists = EmailTemplate::query()->where('key', $data['key'])->when($t !== null, fn ($q) => $q->where('id', '!=', $t->id))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['key' => __('notifications.key_taken')]);
        }

        return DB::transaction(function () use ($t, $data, $form, $app, $design, $userId): EmailTemplate {
            $t ??= new EmailTemplate;
            $t->fill(['key' => $data['key'], 'form_id' => $form?->id, 'application_id' => $app !== null ? $app->id : $form?->application_id, 'design' => $design, 'is_active' => (bool) ($data['active'] ?? true)]);
            $t->save();
            $this->translator->putMany('email_template', $t->id, 'subject', $this->locales((array) ($data['subject'] ?? [])));
            $this->translator->putMany('email_template', $t->id, 'preheader', $this->locales((array) ($data['preheader'] ?? [])));
            $bodies = [];
            foreach ($this->translator->enabledLocales() as $locale) {
                $bodies[$locale] = $this->renderer->compile($design, $locale);
            }
            $this->translator->putMany('email_template', $t->id, 'body_html', $bodies);
            $this->audit->record('notifications.template_saved', 'config', null, 'email_template', $t->id, ['key' => $t->key], $userId);

            return $t->refresh();
        });
    }

    /**
     * Checks the design and returns it normalized.
     *
     * @param  array<string, mixed>  $design
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $preheader
     * @return array<string, mixed>
     */
    public function validate(?Form $form, array $design, array $subject, array $preheader): array
    {
        $errors = [];
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $default = $this->translator->defaultLocale();
        if (trim((string) ($subject[$default] ?? '')) === '') {
            $errors["subject.{$default}"][] = __('notifications.subject_required');
        }
        foreach (['subject' => $subject, 'preheader' => $preheader] as $name => $map) {
            foreach ($map as $locale => $text) {
                if ($text !== null && mb_strlen((string) $text) > 255) {
                    $errors["{$name}.{$locale}"][] = __('validation.max.string', ['attribute' => $name, 'max' => 255]);
                }
                $this->checkText((string) $text, $rt, "{$name}.{$locale}", $errors);
            }
        }
        $style = (array) ($design['style'] ?? []);
        $accent = $this->design->colour($style['accent'] ?? null, 'design.style.accent', $errors);
        $blocks = $this->design->blocks($form, $rt, $design['blocks'] ?? [], self::BLOCKS, 'design.blocks', $errors);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['style' => ['accent' => $accent, 'logo' => (bool) ($style['logo'] ?? true)], 'blocks' => $blocks];
    }

    /**
     * @param  array<string, list<array<mixed>|string>>  $errors  messages by path, as validation errors
     */
    private function checkText(string $text, ?FormRuntime $rt, string $path, array &$errors): void
    {
        $this->design->checkText($text, $rt, $path, $errors);
    }

    /** @param  array<string, mixed>  $map @return array<string, string|null> */
    private function locales(array $map): array
    {
        return $this->design->locales($map);
    }
}
