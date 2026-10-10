<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Views\RelationPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        private readonly RelationPaths $paths,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
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
        $accent = $style['accent'] ?? null;
        if ($accent !== null && (! is_string($accent) || preg_match('/^#[0-9a-f]{6}$/i', $accent) !== 1)) {
            $errors['design.style.accent'][] = __('notifications.bad_colour');
        }
        $blocks = array_values((array) ($design['blocks'] ?? []));
        if (count($blocks) > 100) {
            $errors['design.blocks'][] = __('notifications.too_many_blocks');
        }
        $resolver = $form === null ? null : $this->draftValidator->resolver($this->drafts->normalize($this->drafts->load($form)));
        $out = [];
        foreach ($blocks as $i => $b) {
            $p = "design.blocks.{$i}";
            $type = is_array($b) ? ($b['type'] ?? null) : null;
            if (! in_array($type, self::BLOCKS, true)) {
                $errors["{$p}.type"][] = __('validation.in', ['attribute' => 'type']);

                continue;
            }
            $block = ['uuid' => is_string($b['uuid'] ?? null) ? strtolower($b['uuid']) : (string) Str::uuid7(), 'type' => $type];
            if (in_array($type, ['heading', 'text'], true)) {
                $block['text'] = $this->i18n($b['text'] ?? [], "{$p}.text", $rt, $errors, $type === 'text' ? 5000 : 255);
            }
            if ($type === 'button') {
                $block['label'] = $this->i18n($b['label'] ?? [], "{$p}.label", $rt, $errors, 120);
                $block['link'] = ($b['link'] ?? 'record') === 'url' ? 'url' : 'record';
                $block['url'] = null;
                if ($block['link'] === 'url') {
                    $url = trim((string) ($b['url'] ?? ''));
                    if (preg_match('#^https?://[^\s"<>]+$#i', $url) !== 1 || mb_strlen($url) > 2000) {
                        $errors["{$p}.url"][] = __('notifications.bad_url');
                    }
                    $this->checkText($url, $rt, "{$p}.url", $errors);
                    $block['url'] = $url;
                }
                if ($rt === null && $block['link'] === 'record') {
                    $errors["{$p}.link"][] = __('notifications.record_link_needs_form');
                }
            }
            if ($type === 'fields') {
                $fields = array_values(array_filter((array) ($b['fields'] ?? []), 'is_string'));
                foreach ($fields as $j => $path) {
                    if (! $this->pathOk(strtolower($path), $rt)) {
                        $errors["{$p}.fields.{$j}"][] = __('notifications.unknown_placeholder', ['placeholder' => $path]);
                    }
                }
                $block['fields'] = array_map('strtolower', $fields);
            }
            if ($type === 'table') {
                $repeater = null;
                foreach ($rt === null ? [] : $rt->repeaters as $uuid => $rep) {
                    if ($rep['group']['key'] === ($b['group'] ?? null)) {
                        $repeater = $uuid;
                    }
                }
                if ($repeater === null) {
                    $errors["{$p}.group"][] = __('notifications.unknown_repeater');
                } else {
                    $keys = array_map(static fn ($f) => $f['key'], $rt->rowFields($repeater));
                    foreach ((array) ($b['columns'] ?? []) as $j => $c) {
                        if (! in_array($c, $keys, true)) {
                            $errors["{$p}.columns.{$j}"][] = __('notifications.unknown_column');
                        }
                    }
                }
                $block['group'] = (string) ($b['group'] ?? '');
                $block['columns'] = array_values(array_filter((array) ($b['columns'] ?? []), 'is_string'));
            }
            $condition = $b['condition'] ?? null;
            if ($condition !== null) {
                if ($resolver === null || ! is_array($condition)) {
                    $errors["{$p}.condition"][] = __('notifications.condition_needs_form');
                } else {
                    try {
                        TypeChecker::check($condition, $resolver, 'boolean');
                    } catch (StaticError $e) {
                        $errors["{$p}.condition"][] = $e->getMessage();
                    }
                }
            }
            $block['condition'] = is_array($condition) ? $condition : null;
            $out[] = $block;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['style' => ['accent' => $accent, 'logo' => (bool) ($style['logo'] ?? true)], 'blocks' => $out];
    }

    /**
     * Placeholders of a text must be well formed and, for a form's template, exist in the form.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors  messages by path, as validation errors
     */
    private function checkText(string $text, ?FormRuntime $rt, string $path, array &$errors): void
    {
        foreach (TemplateRenderer::tokens($text) as $token) {
            if (! $this->pathOk($token, $rt)) {
                $errors[$path][] = __('notifications.unknown_placeholder', ['placeholder' => $token]);
            }
        }
    }

    private function pathOk(string $token, ?FormRuntime $rt): bool
    {
        if (! TemplateRenderer::wellFormed($token)) {
            return false;
        }
        if (str_starts_with($token, '@')) {
            return true;
        }

        return $rt !== null && $this->paths->resolve($rt, explode('.', $token)) !== null;
    }

    /**
     * @param  array<string, list<array<mixed>|string>>  $errors  messages by path, as validation errors
     * @return array<string, string>
     */
    private function i18n(mixed $map, string $path, ?FormRuntime $rt, array &$errors, int $max): array
    {
        $out = [];
        foreach ((array) $map as $locale => $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }
            if (mb_strlen($text) > $max) {
                $errors["{$path}.{$locale}"][] = __('validation.max.string', ['attribute' => 'text', 'max' => $max]);
            }
            $this->checkText($text, $rt, "{$path}.{$locale}", $errors);
            $out[(string) $locale] = $text;
        }
        if (($out[$this->translator->defaultLocale()] ?? '') === '') {
            $errors["{$path}.{$this->translator->defaultLocale()}"][] = __('notifications.text_required');
        }

        return $out;
    }

    /** @param  array<string, mixed>  $map @return array<string, string|null> */
    private function locales(array $map): array
    {
        $out = [];
        foreach ($this->translator->enabledLocales() as $locale) {
            $v = $map[$locale] ?? null;
            $out[$locale] = is_string($v) && trim($v) !== '' ? $v : null;
        }

        return $out;
    }
}
