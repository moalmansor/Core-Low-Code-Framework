<?php

declare(strict_types=1);

namespace App\Modules\Experience\Themes;

use Illuminate\Validation\ValidationException;

/**
 * Admin-authored CSS for a theme (specification §4.28, architecture §19.11),
 * sanitized and scoped so it cannot break layout primitives or hide security
 * controls:
 *  - plain rules only: no at-rules (@import, @font-face, @media…), no nesting,
 *    no escapes, no `url()`, `expression()`, `behavior` or `-moz-binding`;
 *  - every selector is prefixed with `.app-content`, so it reaches only the
 *    page content — not the shell, banners or dialogs, which are rendered
 *    outside it — and may not name the security and maintenance banners,
 *    dialogs, toasts or the document root;
 *  - only typographic, colour, border, spacing and shadow properties, with
 *    plain values and without `!important`.
 *
 * The result is served as a stylesheet of its own after the theme (never as
 * an inline style), so the Content-Security-Policy stays strict.
 */
final class CustomCss
{
    public const MAX_LENGTH = 20000;

    public const SCOPE = '.app-content';

    /** @var list<string> */
    public const PROPERTIES = [
        'color', 'background-color', 'border-color', 'border-width', 'border-style', 'border-radius',
        'border-top-color', 'border-bottom-color', 'border-inline-start-color', 'border-inline-end-color',
        'font-weight', 'font-style', 'font-size', 'letter-spacing', 'line-height', 'text-transform', 'text-decoration', 'text-align',
        'padding', 'padding-block', 'padding-inline', 'padding-top', 'padding-bottom', 'padding-inline-start', 'padding-inline-end',
        'margin-block', 'margin-top', 'margin-bottom', 'gap', 'box-shadow', 'outline-color',
    ];

    /** Selectors that would reach controls the custom CSS must never restyle. */
    private const FORBIDDEN = ['security-banner', 'impersonation-banner', 'maintenance-banner', 'banner', 'p-dialog', 'p-toast', 'p-confirm', ':root', 'html', 'body', 'app-shell'];

    /** @throws ValidationException */
    public static function compile(?string $css): ?string
    {
        if ($css === null || trim($css) === '') {
            return null;
        }
        if (mb_strlen($css) > self::MAX_LENGTH) {
            self::fail(__('experience.css_too_long', ['max' => self::MAX_LENGTH]));
        }
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        foreach (['@', '\\', '<', 'url(', 'expression', 'javascript:', 'behavior', '-moz-binding', 'import'] as $banned) {
            if (stripos($source, $banned) !== false) {
                self::fail(__('experience.css_not_allowed', ['what' => $banned]));
            }
        }
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $source, $rules, PREG_SET_ORDER) === false) {
            self::fail(__('experience.css_syntax'));
        }
        $rest = trim((string) preg_replace('/([^{}]+)\{([^{}]*)\}/', '', $source));
        if ($rest !== '') {
            self::fail(__('experience.css_syntax'));
        }
        $out = [];
        foreach ($rules as [, $selectors, $body]) {
            $scoped = [];
            foreach (explode(',', $selectors) as $sel) {
                $sel = trim((string) preg_replace('/\s+/', ' ', $sel));
                if ($sel === '' || preg_match('/^[a-zA-Z0-9 .#:_>+~\-\[\]="\'*()]+$/', $sel) !== 1) {
                    self::fail(__('experience.css_bad_selector', ['selector' => mb_substr($sel, 0, 60)]));
                }
                foreach (self::FORBIDDEN as $f) {
                    if (stripos($sel, $f) !== false) {
                        self::fail(__('experience.css_forbidden_selector', ['selector' => mb_substr($sel, 0, 60)]));
                    }
                }
                $scoped[] = self::SCOPE.' '.$sel;
            }
            $decls = [];
            foreach (explode(';', $body) as $decl) {
                if (trim($decl) === '') {
                    continue;
                }
                $parts = explode(':', $decl, 2);
                $prop = strtolower(trim($parts[0]));
                $value = trim($parts[1] ?? '');
                if (! in_array($prop, self::PROPERTIES, true)) {
                    self::fail(__('experience.css_bad_property', ['property' => mb_substr($prop, 0, 40)]));
                }
                if ($value === '' || mb_strlen($value) > 200 || stripos($value, 'important') !== false || preg_match('/^[a-zA-Z0-9 #%.,()\-+\/"\']+$/', $value) !== 1) {
                    self::fail(__('experience.css_bad_value', ['property' => $prop]));
                }
                $decls[] = "  {$prop}: {$value};";
            }
            if ($decls !== []) {
                $out[] = implode(",\n", $scoped)." {\n".implode("\n", $decls)."\n}";
            }
        }

        return $out === [] ? null : implode("\n\n", $out)."\n";
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['custom_css' => $message]);
    }
}
