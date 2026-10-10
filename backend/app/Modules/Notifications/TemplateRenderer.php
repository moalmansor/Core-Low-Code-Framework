<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\SheetCodec;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\References;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Modules\Views\RelationPaths;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Renders an email template for the recipients of one message (architecture
 * §19.14): in their language, showing a value only when every one of them
 * may see it (a field hidden from any recipient reads as empty, linked
 * records only within each recipient's record scope, a conditional block
 * only when its condition holds for each of them), so To, CC and BCC can
 * share one message without anyone receiving a field they cannot see. Placeholders are references of the
 * expression language: `{{ field }}`, `{{ customer.name }}` (a relation path
 * of up to four hops), or a system value such as `{{ @record_number }}`.
 * Every value is escaped; the result is a self-contained HTML email built
 * from fixed, inline-styled markup, so no stored HTML is ever trusted.
 */
final class TemplateRenderer
{
    /** The product's default primary colour (frontend/src/theme/tokens.css), for an unbranded installation. */
    public const DEFAULT_ACCENT = '#1a6fd4';

    public const SYSTEM = ['@record_number', '@status', '@created_at', '@updated_at', '@link', '@form', '@recipient', '@today', '@app'];

    public const TOKEN = '/\{\{\s*([@a-z0-9_.]+)\s*\}\}/i';

    private const PATH = '/^(@[a-z_]+|[a-z][a-z0-9_]{0,47}(\.[a-z][a-z0-9_]{0,47}){0,3}(\.@[a-z_]+)?)$/';

    public function __construct(
        private readonly Translator $translator,
        private readonly RelationPaths $paths,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordStore $store,
        private readonly RuleRuntime $rules,
        private readonly SheetCodec $codec,
        private readonly References $refs,
        private readonly SettingsService $settings,
    ) {}

    /** @return list<string> the placeholders of a text, as written */
    public static function tokens(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /** Whether a placeholder is well formed (its target is checked against the form separately). */
    public static function wellFormed(string $token): bool
    {
        return preg_match(self::PATH, $token) === 1 && (! str_starts_with($token, '@') || in_array($token, self::SYSTEM, true));
    }

    /**
     * @param  array<string, mixed>  $design
     * @param  array<string, string|null>  $subject  per locale
     * @param  array<string, string|null>  $preheader  per locale
     * @param  list<User>  $viewers  whose field access applies (the recipients, and the rule's author for addresses outside the system)
     * @return array{subject: string, html: string, text: string, preheader: string}
     */
    public function render(array $design, array $subject, array $preheader, ?FormRuntime $rt, ?int $recordId, array $viewers, string $locale, ?string $recipientName = null): array
    {
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            $ctx = new RenderContext($rt, $recordId, $viewers, $locale, $recipientName);
            $subjectText = $this->text((string) ($this->translator->pick($subject, $locale) ?? ''), $ctx, false);
            $pre = $this->text((string) ($this->translator->pick($preheader, $locale) ?? ''), $ctx, false);
            $body = '';
            foreach ((array) ($design['blocks'] ?? []) as $block) {
                if (is_array($block) && $this->shown($block, $ctx)) {
                    $body .= $this->block($block, $ctx, $design);
                }
            }
            $html = $this->layout($body, $pre, $design, $locale, $subjectText);
            $text = trim(html_entity_decode(strip_tags((string) preg_replace(['/<br\s*\/?>/i', '/<\/(p|tr|h1|h2|div)>/i'], ["\n", "\n"], $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return ['subject' => mb_substr(trim(preg_replace('/\s+/u', ' ', $subjectText) ?? ''), 0, 255), 'html' => $html, 'text' => $text, 'preheader' => trim($pre)];
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * Compiles the design into an HTML body for one language with the
     * placeholders left as they are (stored as the template's `body_html`).
     *
     * @param  array<string, mixed>  $design
     */
    public function compile(array $design, string $locale): string
    {
        $parts = [];
        foreach ((array) ($design['blocks'] ?? []) as $b) {
            $parts[] = match ($b['type'] ?? '') {
                'heading' => '<h2>'.e((string) ($this->translator->pick($b['text'] ?? [], $locale) ?? '')).'</h2>',
                'text' => '<p>'.nl2br(e((string) ($this->translator->pick($b['text'] ?? [], $locale) ?? ''))).'</p>',
                'button' => '<p><a>'.e((string) ($this->translator->pick($b['label'] ?? [], $locale) ?? '')).'</a></p>',
                'fields' => '<ul>'.implode('', array_map(static fn ($p) => '<li>{{ '.e((string) $p).' }}</li>', (array) ($b['fields'] ?? []))).'</ul>',
                'table' => '<table><tr>'.implode('', array_map(static fn ($c) => '<td>{{ '.e((string) ($b['group'] ?? '')).'.'.e((string) $c).' }}</td>', (array) ($b['columns'] ?? []))).'</tr></table>',
                'divider' => '<hr>',
                default => '',
            };
        }

        return implode("\n", array_filter($parts));
    }

    /** @param  array<string, mixed>  $block */
    private function shown(array $block, RenderContext $ctx): bool
    {
        $ast = $block['condition'] ?? null;
        if (! is_array($ast)) {
            return true;
        }
        if ($ctx->rt === null || $ctx->recordId === null || $ctx->viewers === []) {
            return false;
        }
        foreach ($ctx->viewers as $i => $viewer) {
            if (! $this->rules->holds($ctx->rt, $ast, $this->visibleValues($ctx, $i), $viewer, 'view')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $b
     * @param  array<string, mixed>  $design
     */
    private function block(array $b, RenderContext $ctx, array $design): string
    {
        $accent = $this->accent($design);

        return match ($b['type'] ?? '') {
            'heading' => '<h2 style="margin:0 0 12px;font-size:20px;line-height:1.3;color:#111827">'.$this->text((string) ($this->translator->pick($b['text'] ?? [], $ctx->locale) ?? ''), $ctx, true).'</h2>',
            'text' => $this->paragraphs((string) ($this->translator->pick($b['text'] ?? [], $ctx->locale) ?? ''), $ctx),
            'button' => $this->button($b, $ctx, $accent),
            'fields' => $this->fields($b, $ctx),
            'table' => $this->table($b, $ctx),
            'divider' => '<hr style="border:0;border-top:1px solid #e5e7eb;margin:20px 0">',
            'spacer' => '<div style="height:16px;line-height:16px">&#8203;</div>',
            default => '',
        };
    }

    /**
     * Escaped text with its placeholders filled; `**bold**` in the template is
     * the only markup. Markup is read from the template, never from filled
     * values, and a bold span that fills to nothing leaves no markers behind.
     */
    private function text(string $source, RenderContext $ctx, bool $html): string
    {
        $out = '';
        foreach (preg_split('/(\*\*.+?\*\*)/su', $source, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (strlen($part) > 4 && str_starts_with($part, '**') && str_ends_with($part, '**')) {
                $inner = $this->fill(substr($part, 2, -2), $ctx, $html);
                $out .= $html && trim($inner) !== '' ? '<strong>'.$inner.'</strong>' : $inner;
            } else {
                $out .= $this->fill($part, $ctx, $html);
            }
        }

        return $out;
    }

    private function fill(string $source, RenderContext $ctx, bool $html): string
    {
        $out = '';
        $offset = 0;
        preg_match_all(self::TOKEN, $source, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $match) {
            [$whole, $pos] = $match[0];
            $out .= $html ? e(substr($source, $offset, $pos - $offset)) : substr($source, $offset, $pos - $offset);
            $value = $this->value(strtolower($match[1][0]), $ctx);
            $out .= $html ? e($value) : $value;
            $offset = $pos + strlen($whole);
        }

        return $out.($html ? e(substr($source, $offset)) : substr($source, $offset));
    }

    private function paragraphs(string $source, RenderContext $ctx): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim($source)) ?: [] as $para) {
            if (trim($para) !== '') {
                $html .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#1f2937">'.nl2br($this->text($para, $ctx, true)).'</p>';
            }
        }

        return $html;
    }

    /** @param  array<string, mixed>  $b */
    private function button(array $b, RenderContext $ctx, string $accent): string
    {
        $label = $this->text((string) ($this->translator->pick($b['label'] ?? [], $ctx->locale) ?? ''), $ctx, true);
        $href = ($b['link'] ?? 'record') === 'url' ? $this->url((string) ($b['url'] ?? ''), $ctx) : $this->value('@link', $ctx);
        if ($href === '' || preg_match('#^https?://#i', $href) !== 1 || $label === '') {
            return '';
        }

        return '<p style="margin:20px 0"><a href="'.e($href).'" style="display:inline-block;background:'.$accent.';color:#ffffff;text-decoration:none;font-weight:600;padding:10px 18px;border-radius:6px;font-size:15px">'.$label.'</a></p>';
    }

    /** A URL with its placeholders filled and encoded. */
    private function url(string $source, RenderContext $ctx): string
    {
        return (string) preg_replace_callback(self::TOKEN, fn (array $m) => rawurlencode($this->value(strtolower($m[1]), $ctx)), $source);
    }

    /** @param  array<string, mixed>  $b */
    private function fields(array $b, RenderContext $ctx): string
    {
        if ($ctx->rt === null || $ctx->recordId === null || $ctx->viewers === []) {
            return '';
        }
        $rows = '';
        foreach ((array) ($b['fields'] ?? []) as $path) {
            $segments = explode('.', (string) $path);
            $r = null;
            foreach ($ctx->viewers as $viewer) {
                $r = $this->paths->resolve($ctx->rt, $segments, $viewer);
                if ($r === null) {
                    break;
                }
            }
            if ($r === null) {
                continue;
            }
            $label = $r['field'] === null
                ? __('views.system_'.$r['system'])
                : $this->translator->labelOf($r['field']['i18n']['label'] ?? null, (string) $r['field']['key']);
            $value = $this->value(strtolower((string) $path), $ctx);
            $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#6b7280;font-size:14px;vertical-align:top;white-space:nowrap">'.e($label).'</td>'
                .'<td style="padding:6px 0;font-size:14px;color:#111827">'.($value === '' ? '<span style="color:#9ca3af">—</span>' : nl2br(e($value))).'</td></tr>';
        }

        return $rows === '' ? '' : '<table role="presentation" style="border-collapse:collapse;margin:8px 0 16px">'.$rows.'</table>';
    }

    /** A repeater's rows as a table, with the columns the viewer may see. @param  array<string, mixed>  $b */
    private function table(array $b, RenderContext $ctx): string
    {
        $rt = $ctx->rt;
        if ($rt === null || $ctx->recordId === null || $ctx->viewers === []) {
            return '';
        }
        $repUuid = null;
        foreach ($rt->repeaters as $uuid => $rep) {
            if ($rep['group']['key'] === ($b['group'] ?? null)) {
                $repUuid = $uuid;
            }
        }
        $levels = $this->levels($ctx);
        if ($repUuid === null || ($levels[$repUuid] ?? 'editable') === 'hidden') {
            return '';
        }
        $byKey = [];
        foreach ($rt->rowFields($repUuid) as $f) {
            $byKey[$f['key']] = $f;
        }
        $columns = array_values(array_filter(array_map(static fn ($k) => $byKey[$k] ?? null, (array) ($b['columns'] ?? [])), static fn ($f) => $f !== null && ($levels[$f['uuid']] ?? 'editable') !== 'hidden'));
        $record = $this->store->findById($rt, $ctx->recordId);
        $rows = array_slice((array) ($record['values'][$rt->repeaters[$repUuid]['group']['key']] ?? []), 0, 200);
        if ($columns === [] || $rows === []) {
            return '';
        }
        $titles = [];
        foreach ($columns as $f) {
            if ($rt->targetTable($f) !== null) {
                $uuids = [];
                foreach ($rows as $row) {
                    $uuids = [...$uuids, ...array_filter((array) ($row[$f['key']] ?? []), 'is_string')];
                }
                $titles[$f['uuid']] = $this->refs->titles($rt, $f, array_values(array_unique($uuids)));
            }
        }
        $head = '';
        foreach ($columns as $f) {
            $head .= '<th style="text-align:start;padding:6px 8px;border-bottom:2px solid #e5e7eb;font-size:13px;color:#374151">'.e($this->translator->labelOf($f['i18n']['label'] ?? null, (string) $f['key'])).'</th>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($columns as $f) {
                $cell = $this->codec->toCell($rt, $f, $row[$f['key']] ?? null, $titles[$f['uuid']] ?? [], []);
                $body .= '<td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;font-size:13px">'.e(is_bool($cell) ? ($cell ? '✓' : '') : (string) ($cell ?? '')).'</td>';
            }
            $body .= '</tr>';
        }

        return '<table role="presentation" style="border-collapse:collapse;width:100%;margin:8px 0 16px"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }

    /** The display text of a placeholder for this recipient ('' when unknown, hidden or empty). */
    private function value(string $token, RenderContext $ctx): string
    {
        if (array_key_exists($token, $ctx->cache)) {
            return $ctx->cache[$token];
        }
        $rt = $ctx->rt;
        $value = match (true) {
            $token === '@link' => $rt !== null && $ctx->recordId !== null
                ? rtrim((string) config('app.url'), '/').'/app/'.$rt->form->uuid.'/'.strtolower((string) DB::table($rt->table)->where('id', $ctx->recordId)->value('uuid'))
                : rtrim((string) config('app.url'), '/'),
            $token === '@form' => $rt === null ? '' : $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key),
            $token === '@recipient' => (string) ($ctx->recipientName ?? ''),
            $token === '@today' => Carbon::now((string) $this->settings->get('formats', 'timezone'))->format('Y-m-d'),
            $token === '@app' => $this->appName(),
            $rt === null || $ctx->recordId === null || $ctx->viewers === [] => '',
            default => $this->shared($rt, $token, $ctx),
        };

        return $ctx->cache[$token] = $value;
    }

    /** A path's text as every viewer sees it, or '' when they would not all see the same. */
    private function shared(FormRuntime $rt, string $token, RenderContext $ctx): string
    {
        $seen = null;
        foreach ($ctx->viewers as $viewer) {
            $v = self::flatten($this->paths->values($rt, explode('.', $token), [$ctx->recordId], $viewer)[$ctx->recordId] ?? null);
            if ($seen !== null && $v !== $seen) {
                return '';
            }
            $seen = $v;
        }

        return (string) $seen;
    }

    /** @return array<string, mixed> the record's values with the fields one viewer cannot see emptied */
    private function visibleValues(RenderContext $ctx, int $viewer): array
    {
        $record = $this->store->findById($ctx->rt, (int) $ctx->recordId);
        $values = $record['values'] ?? [];
        foreach ($this->levelsOf($ctx, $viewer) as $uuid => $level) {
            $key = $ctx->rt->fields[$uuid]['key'] ?? null;
            if ($level === 'hidden' && $key !== null) {
                $values[$key] = null;
            }
        }

        return $values;
    }

    /** @return array<string, string> field and group access of one viewer */
    private function levelsOf(RenderContext $ctx, int $viewer): array
    {
        if (! isset($ctx->levels[$viewer])) {
            $status = DB::table($ctx->rt->table)->where('id', $ctx->recordId)->value('status_id');
            $ctx->levels[$viewer] = $this->fieldAccess->resolve($ctx->viewers[$viewer], $ctx->rt->form->id, $ctx->rt->form->uuid, $ctx->rt->definition, 'view', $status === null ? null : (int) $status)['fields'];
        }

        return $ctx->levels[$viewer];
    }

    /** @return array<string, string> the most restrictive access across the viewers: hidden for one is hidden */
    private function levels(RenderContext $ctx): array
    {
        $out = [];
        foreach (array_keys($ctx->viewers) as $i) {
            foreach ($this->levelsOf($ctx, $i) as $uuid => $level) {
                if ($level === 'hidden' || ! isset($out[$uuid])) {
                    $out[$uuid] = $level;
                }
            }
        }

        return $out;
    }

    private function appName(): string
    {
        return $this->translator->get('organization', app(TenantContext::class)->organizationId(), 'name') ?? (string) config('app.name');
    }

    private static function flatten(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '✓' : '',
            is_array($value) => implode(', ', array_map(static fn ($v) => self::flatten($v), $value)),
            default => (string) $value,
        };
    }

    /** @param  array<string, mixed>  $design */
    private function accent(array $design): string
    {
        $c = $design['style']['accent'] ?? null;
        if (is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) === 1) {
            return $c;
        }
        $brand = $this->settings->get('branding', 'primary_color');

        return is_string($brand) && preg_match('/^#[0-9a-f]{6}$/i', $brand) === 1 ? $brand : self::DEFAULT_ACCENT;
    }

    /** @param  array<string, mixed>  $design */
    private function layout(string $body, string $preheader, array $design, string $locale, string $title): string
    {
        $rtl = in_array($locale, ['ar', 'fa', 'he', 'ur'], true);
        $dir = $rtl ? 'rtl' : 'ltr';
        $accent = $this->accent($design);
        $logo = ($design['style']['logo'] ?? true) && $this->settings->get('branding', 'logo_file') !== null
            ? '<img src="'.e(rtrim((string) config('app.url'), '/').'/api/v1/branding/logo').'" alt="" height="36" style="display:block;height:36px;border:0;margin-bottom:16px">'
            : '';
        $hidden = $preheader === '' ? '' : '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($preheader).'</div>';

        return '<!doctype html><html lang="'.e($locale).'" dir="'.$dir.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.e($title).'</title></head>'
            .'<body style="margin:0;padding:0;background:#f4f5f7;font-family:Segoe UI,Tahoma,Arial,sans-serif">'.$hidden
            .'<table role="presentation" width="100%" style="border-collapse:collapse;background:#f4f5f7"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="600" style="border-collapse:collapse;max-width:600px;width:100%;background:#ffffff;border-radius:8px;border-top:4px solid '.$accent.'" dir="'.$dir.'">'
            .'<tr><td style="padding:28px 32px;text-align:'.($rtl ? 'right' : 'left').'">'.$logo.$body.'</td></tr></table>'
            .'</td></tr></table></body></html>';
    }
}
