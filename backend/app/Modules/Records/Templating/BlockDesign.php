<?php

declare(strict_types=1);

namespace App\Modules\Records\Templating;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Support\Str;

/**
 * Checks a template design (architecture §19.14): an ordered list of blocks —
 * heading, text, button, field list, repeater table, divider, spacer, page
 * break — each optionally shown only when a condition holds. Email templates
 * and HTML document templates share it, each allowing its own block types.
 * Texts are per language with `{{ placeholders }}` that must name a system
 * value or, for a form's template, a field or relation path of that form.
 */
final class BlockDesign
{
    public const MAX_BLOCKS = 100;

    public function __construct(
        private readonly Translator $translator,
        private readonly TemplateValues $values,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
    ) {}

    /**
     * The blocks normalized; problems are added to $errors by path.
     *
     * @param  list<string>  $types  the block types this kind of template allows
     * @param  array<string, list<array<mixed>|string>>  $errors  messages by path, as validation errors
     * @return list<array<string, mixed>>
     */
    public function blocks(?Form $form, ?FormRuntime $rt, mixed $blocks, array $types, string $prefix, array &$errors): array
    {
        $blocks = array_values((array) $blocks);
        if (count($blocks) > self::MAX_BLOCKS) {
            $errors[$prefix][] = __('templates.too_many_blocks');
        }
        $resolver = $form === null ? null : $this->draftValidator->resolver($this->drafts->normalize($this->drafts->load($form)));
        $out = [];
        foreach ($blocks as $i => $b) {
            $p = "{$prefix}.{$i}";
            $type = is_array($b) ? ($b['type'] ?? null) : null;
            if (! is_array($b) || ! in_array($type, $types, true)) {
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
                        $errors["{$p}.url"][] = __('templates.bad_url');
                    }
                    $this->checkText($url, $rt, "{$p}.url", $errors);
                    $block['url'] = $url;
                }
                if ($rt === null && $block['link'] === 'record') {
                    $errors["{$p}.link"][] = __('templates.record_link_needs_form');
                }
            }
            if ($type === 'fields') {
                $fields = array_values(array_filter((array) ($b['fields'] ?? []), 'is_string'));
                foreach ($fields as $j => $path) {
                    if (! $this->values->pathOk(strtolower($path), $rt)) {
                        $errors["{$p}.fields.{$j}"][] = __('templates.unknown_placeholder', ['placeholder' => $path]);
                    }
                }
                $block['fields'] = array_map('strtolower', $fields);
            }
            if ($type === 'table') {
                $repeater = $this->values->repeater($rt, (string) ($b['group'] ?? ''));
                if ($repeater === null || $rt === null) {
                    $errors["{$p}.group"][] = __('templates.unknown_repeater');
                } else {
                    $keys = array_map(static fn ($f) => $f['key'], $rt->rowFields($repeater));
                    foreach ((array) ($b['columns'] ?? []) as $j => $c) {
                        if (! in_array($c, $keys, true)) {
                            $errors["{$p}.columns.{$j}"][] = __('templates.unknown_column');
                        }
                    }
                }
                $block['group'] = (string) ($b['group'] ?? '');
                $block['columns'] = array_values(array_filter((array) ($b['columns'] ?? []), 'is_string'));
            }
            $condition = $b['condition'] ?? null;
            if ($condition !== null) {
                if ($resolver === null || ! is_array($condition)) {
                    $errors["{$p}.condition"][] = __('templates.condition_needs_form');
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

        return $out;
    }

    /** A colour as `#rrggbb`, or null; anything else is an error at $path. @param  array<string, list<array<mixed>|string>>  $errors */
    public function colour(mixed $value, string $path, array &$errors): ?string
    {
        if ($value !== null && (! is_string($value) || preg_match('/^#[0-9a-f]{6}$/i', $value) !== 1)) {
            $errors[$path][] = __('templates.bad_colour');

            return null;
        }

        return $value;
    }

    /**
     * Placeholders of a text must be well formed and, for a form's template, exist in the form.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors  messages by path, as validation errors
     */
    public function checkText(string $text, ?FormRuntime $rt, string $path, array &$errors): void
    {
        foreach (TemplateValues::tokens($text) as $token) {
            if (! $this->values->pathOk($token, $rt)) {
                $errors[$path][] = __('templates.unknown_placeholder', ['placeholder' => $token]);
            }
        }
    }

    /**
     * A text per language, required in the default language.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors  messages by path, as validation errors
     * @return array<string, string>
     */
    public function i18n(mixed $map, string $path, ?FormRuntime $rt, array &$errors, int $max): array
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
            $errors["{$path}.{$this->translator->defaultLocale()}"][] = __('templates.text_required');
        }

        return $out;
    }

    /** One value per enabled language, empty texts as null. @param  array<string, mixed>  $map @return array<string, string|null> */
    public function locales(array $map): array
    {
        $out = [];
        foreach ($this->translator->enabledLocales() as $locale) {
            $v = $map[$locale] ?? null;
            $out[$locale] = is_string($v) && trim($v) !== '' ? $v : null;
        }

        return $out;
    }
}
