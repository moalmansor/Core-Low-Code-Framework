<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Experience\Themes\Themes;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Templating\RenderContext;
use App\Modules\Records\Templating\TemplateValues;
use Illuminate\Support\Facades\App;

/**
 * Renders an email template for the recipients of one message (architecture
 * §19.14), in their language. Values come from TemplateValues, which shows a
 * value only when every recipient may see it, so To, CC and BCC can share one
 * message without anyone receiving a field they cannot see. Every value is
 * escaped; the result is a self-contained HTML email built from fixed,
 * inline-styled markup, so no stored HTML is ever trusted.
 */
final class TemplateRenderer
{
    public const SYSTEM = TemplateValues::SYSTEM;

    public function __construct(
        private readonly Translator $translator,
        private readonly TemplateValues $values,
        private readonly SettingsService $settings,
    ) {}

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
            $ctx = $this->values->context($rt, $recordId, $viewers, $locale, $recipientName);
            $subjectText = $this->values->plain((string) ($this->translator->pick($subject, $locale) ?? ''), $ctx);
            $pre = $this->values->plain((string) ($this->translator->pick($preheader, $locale) ?? ''), $ctx);
            $body = '';
            foreach ((array) ($design['blocks'] ?? []) as $block) {
                if (is_array($block) && $this->values->shown($block, $ctx)) {
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

    /**
     * @param  array<string, mixed>  $b
     * @param  array<string, mixed>  $design
     */
    private function block(array $b, RenderContext $ctx, array $design): string
    {
        return match ($b['type'] ?? '') {
            'heading' => '<h2 style="margin:0 0 12px;font-size:20px;line-height:1.3;color:#111827">'.$this->text((string) ($this->translator->pick($b['text'] ?? [], $ctx->locale) ?? ''), $ctx).'</h2>',
            'text' => $this->paragraphs((string) ($this->translator->pick($b['text'] ?? [], $ctx->locale) ?? ''), $ctx),
            'button' => $this->button($b, $ctx, $this->values->accent($design)),
            'fields' => $this->fields($b, $ctx),
            'table' => $this->table($b, $ctx),
            'divider' => '<hr style="border:0;border-top:1px solid #e5e7eb;margin:20px 0">',
            'spacer' => '<div style="height:16px;line-height:16px">&#8203;</div>',
            default => '',
        };
    }

    /** Escaped text with its placeholders filled and the template's bold runs in <strong>. */
    private function text(string $source, RenderContext $ctx): string
    {
        return implode('', array_map(static fn (array $r) => $r['bold'] ? '<strong>'.e($r['text']).'</strong>' : e($r['text']), $this->values->runs($source, $ctx)));
    }

    private function paragraphs(string $source, RenderContext $ctx): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim($source)) ?: [] as $para) {
            if (trim($para) !== '') {
                $html .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#1f2937">'.nl2br($this->text($para, $ctx)).'</p>';
            }
        }

        return $html;
    }

    /** @param  array<string, mixed>  $b */
    private function button(array $b, RenderContext $ctx, string $accent): string
    {
        $label = $this->text((string) ($this->translator->pick($b['label'] ?? [], $ctx->locale) ?? ''), $ctx);
        $href = ($b['link'] ?? 'record') === 'url' ? $this->values->url((string) ($b['url'] ?? ''), $ctx) : $this->values->value('@link', $ctx);
        if ($href === '' || preg_match('#^https?://#i', $href) !== 1 || $label === '') {
            return '';
        }

        return '<p style="margin:20px 0"><a href="'.e($href).'" style="display:inline-block;background:'.$accent.';color:#ffffff;text-decoration:none;font-weight:600;padding:10px 18px;border-radius:6px;font-size:15px">'.$label.'</a></p>';
    }

    /** @param  array<string, mixed>  $b */
    private function fields(array $b, RenderContext $ctx): string
    {
        $rows = '';
        foreach ($this->values->fieldRows(array_values(array_map('strval', (array) ($b['fields'] ?? []))), $ctx) as $r) {
            $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#6b7280;font-size:14px;vertical-align:top;white-space:nowrap">'.e($r['label']).'</td>'
                .'<td style="padding:6px 0;font-size:14px;color:#111827">'.($r['value'] === '' ? '<span style="color:#9ca3af">—</span>' : nl2br(e($r['value']))).'</td></tr>';
        }

        return $rows === '' ? '' : '<table role="presentation" style="border-collapse:collapse;margin:8px 0 16px">'.$rows.'</table>';
    }

    /** A repeater's rows as a table, with the columns every recipient may see. @param  array<string, mixed>  $b */
    private function table(array $b, RenderContext $ctx): string
    {
        $t = $this->values->table((string) ($b['group'] ?? ''), array_values(array_map('strval', (array) ($b['columns'] ?? []))), $ctx);
        if ($t === null || $t['rows'] === []) {
            return '';
        }
        $head = '';
        foreach ($t['head'] as $label) {
            $head .= '<th style="text-align:start;padding:6px 8px;border-bottom:2px solid #e5e7eb;font-size:13px;color:#374151">'.e($label).'</th>';
        }
        $body = '';
        foreach ($t['rows'] as $row) {
            $body .= '<tr>'.implode('', array_map(static fn (string $c) => '<td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;font-size:13px">'.e($c).'</td>', $row)).'</tr>';
        }

        return '<table role="presentation" style="border-collapse:collapse;width:100%;margin:8px 0 16px"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }

    /** @param  array<string, mixed>  $design */
    private function layout(string $body, string $preheader, array $design, string $locale, string $title): string
    {
        $rtl = in_array($locale, ['ar', 'fa', 'he', 'ur'], true);
        $dir = $rtl ? 'rtl' : 'ltr';
        $accent = $this->values->accent($design);
        // The default theme's email artwork (ADR-0055) replaces the logo at the top, and adds a footer image.
        $theme = app(Themes::class)->organizationDefault();
        $art = $theme === null ? [] : app(Themes::class)->assets($theme);
        $logo = ! ($design['style']['logo'] ?? true) ? '' : (isset($art['email_header'])
            ? '<img src="'.e((string) $art['email_header']).'" alt="" style="display:block;max-width:100%;height:auto;border:0;margin-bottom:16px">'
            : ($this->settings->get('branding', 'logo_file') !== null
                ? '<img src="'.e(rtrim((string) config('app.url'), '/').'/api/v1/branding/logo').'" alt="" height="36" style="display:block;height:36px;border:0;margin-bottom:16px">'
                : ''));
        $footer = isset($art['email_footer']) ? '<img src="'.e((string) $art['email_footer']).'" alt="" style="display:block;max-width:100%;height:auto;border:0;margin-top:16px">' : '';
        $hidden = $preheader === '' ? '' : '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($preheader).'</div>';

        return '<!doctype html><html lang="'.e($locale).'" dir="'.$dir.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.e($title).'</title></head>'
            .'<body style="margin:0;padding:0;background:#f4f5f7;font-family:Segoe UI,Tahoma,Arial,sans-serif">'.$hidden
            .'<table role="presentation" width="100%" style="border-collapse:collapse;background:#f4f5f7"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="600" style="border-collapse:collapse;max-width:600px;width:100%;background:#ffffff;border-radius:8px;border-top:4px solid '.$accent.'" dir="'.$dir.'">'
            .'<tr><td style="padding:28px 32px;text-align:'.($rtl ? 'right' : 'left').'">'.$logo.$body.$footer.'</td></tr></table>'
            .'</td></tr></table></body></html>';
    }
}
