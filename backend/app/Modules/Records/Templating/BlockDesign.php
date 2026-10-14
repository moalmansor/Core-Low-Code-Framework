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
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Str;

/**
 * Checks a template design (architecture §19.14): an ordered list of blocks —
 * heading, text, button, field list, repeater table, table of linked
 * records, signature lines, QR code or barcode, divider, spacer, page
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
        private readonly FormRuntimes $runtimes,
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
                $fields = [];
                foreach (array_values(array_filter((array) ($b['fields'] ?? []), 'is_string')) as $j => $entry) {
                    [$path, $spec] = TemplateValues::split($entry);
                    if (! $this->values->pathOk($path, $rt)) {
                        $errors["{$p}.fields.{$j}"][] = __('templates.unknown_placeholder', ['placeholder' => $path]);
                    }
                    $fields[] = $this->entry($path, $spec, "{$p}.fields.{$j}", $errors);
                }
                $block['fields'] = $fields;
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
                $block['formats'] = [];
                foreach ((array) ($b['formats'] ?? []) as $column => $spec) {
                    if (is_string($spec) && trim($spec) !== '' && in_array($column, $block['columns'], true)) {
                        $block['formats'][(string) $column] = ValueFormat::parse($spec) === null ? $this->badFormat("{$p}.formats.{$column}", $errors) : ValueFormat::spec((array) ValueFormat::parse($spec));
                    }
                }
                $block['formats'] = (object) $block['formats'];
            }
            if ($type === 'related') {
                $source = is_string($b['source'] ?? null) && Str::isUuid($b['source']) ? $this->runtimes->forUuid(strtolower($b['source'])) : null;
                $via = $source === null ? null : ($source->fields[$source->keys[(string) ($b['via'] ?? '')] ?? ''] ?? null);
                if ($rt === null || $source === null || $via === null || $source->targetFormUuid($via) !== $rt->form->uuid || $source->isMultiReference($via)) {
                    $errors["{$p}.via"][] = __('templates.unknown_link');
                }
                $columns = [];
                foreach (array_values(array_filter((array) ($b['columns'] ?? []), 'is_string')) as $j => $entry) {
                    [$path, $spec] = TemplateValues::split($entry);
                    if ($source === null || ! $this->values->pathOk($path, $source)) {
                        $errors["{$p}.columns.{$j}"][] = __('templates.unknown_column');
                    }
                    $columns[] = $this->entry($path, $spec, "{$p}.columns.{$j}", $errors);
                }
                if ($columns === []) {
                    $errors["{$p}.columns"][] = __('templates.columns_required');
                }
                $block['source'] = $source === null ? null : strtolower($source->form->uuid);
                $block['via'] = (string) ($b['via'] ?? '');
                $block['columns'] = $columns;
                $block['limit'] = max(1, min(TemplateValues::MAX_ROWS, (int) ($b['limit'] ?? 50)));
            }
            if ($type === 'signature') {
                $people = [];
                foreach (array_values((array) ($b['signatories'] ?? [])) as $j => $person) {
                    $person = (array) $person;
                    $people[] = [
                        'label' => $this->i18n($person['label'] ?? [], "{$p}.signatories.{$j}.label", $rt, $errors, 120),
                        'name' => $this->optional($person['name'] ?? [], "{$p}.signatories.{$j}.name", $rt, $errors, 255),
                    ];
                }
                if ($people === [] || count($people) > 4) {
                    $errors["{$p}.signatories"][] = __('templates.signatories_count');
                }
                $block['signatories'] = $people;
                $block['date'] = (bool) ($b['date'] ?? true);
            }
            if ($type === 'code') {
                $block['kind'] = ($b['kind'] ?? 'qr') === 'barcode' ? 'barcode' : 'qr';
                $block['content'] = ($b['content'] ?? 'link') === 'number' ? 'number' : 'link';
                $block['size'] = in_array($b['size'] ?? 'medium', ['small', 'medium', 'large'], true) ? ($b['size'] ?? 'medium') : 'medium';
                $block['caption'] = (bool) ($b['caption'] ?? true);
                if ($rt === null) {
                    $errors["{$p}.content"][] = __('templates.code_needs_form');
                }
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

    /**
     * A colour as `#rrggbb`, or null; anything else is an error at $path.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors
     */
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
        foreach (TemplateValues::specs($text) as $spec) {
            if (ValueFormat::parse(ltrim($spec, '| ')) === null) {
                $this->badFormat($path, $errors);
            }
        }
    }

    /**
     * A text per language that may be left empty in every language.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array<string, string>
     */
    public function optional(mixed $map, string $path, ?FormRuntime $rt, array &$errors, int $max): array
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

        return $out;
    }

    /**
     * A path with its formats as stored: `amount|currency:SAR`.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors
     */
    private function entry(string $path, string $spec, string $at, array &$errors): string
    {
        if ($spec === '') {
            return $path;
        }
        $parsed = ValueFormat::parse($spec);
        if ($parsed === null) {
            $this->badFormat($at, $errors);

            return $path;
        }

        return $parsed === [] ? $path : $path.'|'.ValueFormat::spec($parsed);
    }

    /**
     * Notes a format that is not one of the list.
     *
     * @param  array<string, list<array<mixed>|string>>  $errors
     */
    private function badFormat(string $path, array &$errors): string
    {
        $errors[$path][] = __('templates.bad_format');

        return '';
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
