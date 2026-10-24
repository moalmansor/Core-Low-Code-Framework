<?php

declare(strict_types=1);

namespace App\Modules\Experience\Themes;

use App\Support\Color\Contrast;
use Illuminate\Validation\ValidationException;

/**
 * The tokens a theme may set (specification §4.28, architecture §19.11) and
 * the WCAG 2.1 checks they must pass. A token left empty keeps the product
 * theme's value (frontend/src/theme/tokens.css, mirrored in DEFAULTS).
 *
 * Contrast is checked separately in light and dark mode. A colour read as
 * text — body and muted text on the page and surfaces; primary, link,
 * success, warning and danger on surfaces — must reach 4.5:1 or the theme is
 * refused, with the nearest readable shade offered. Weaker contrasts that are
 * not text (a border that barely shows, a surface the same as the page) are
 * warnings the admin must acknowledge before saving.
 */
final class ThemeTokens
{
    /** Colour tokens, as in tokens.css. */
    public const COLORS = ['primary', 'accent', 'success', 'warning', 'danger', 'page', 'surface', 'subtle', 'border'];

    public const MODES = ['light', 'dark'];

    /** @var array<string, array<string, string>> */
    public const DEFAULTS = [
        'light' => [
            'primary' => '#1a6fd4', 'accent' => '#107c52', 'success' => '#107c52', 'warning' => '#b45309', 'danger' => '#d92d20',
            'page' => '#f4f6f9', 'surface' => '#ffffff', 'subtle' => '#f8fafc', 'border' => '#e3e8ef', 'text' => '#1f2937', 'muted' => '#627289',
        ],
        'dark' => [
            'primary' => '#4d96e8', 'accent' => '#35a775', 'success' => '#35a775', 'warning' => '#d9941f', 'danger' => '#f2635c',
            'page' => '#0f1419', 'surface' => '#171e26', 'subtle' => '#1e2733', 'border' => '#2a3441', 'text' => '#e6edf3', 'muted' => '#93a1b1',
        ],
    ];

    public const RADIUS = ['square', 'normal', 'rounded'];

    public const DENSITY = ['compact', 'normal', 'comfortable'];

    public const SHADOW = ['none', 'soft', 'strong'];

    public const FONTS = ['plex', 'system'];

    /**
     * Built-in starting points (system rows of code, not business content):
     * each passes every check in both modes (tested).
     *
     * @var array<string, array<string, mixed>>
     */
    public const PRESETS = [
        'standard' => [],
        'ocean' => ['colors' => ['primary' => ['light' => '#0b6e99', 'dark' => '#4fb3df'], 'accent' => ['light' => '#0b6e99', 'dark' => '#4fb3df'], 'page' => ['light' => '#f5f9fb', 'dark' => '#0d151b']], 'radius' => 'rounded'],
        'forest' => ['colors' => ['primary' => ['light' => '#2f6b3a', 'dark' => '#6cbf7b'], 'accent' => ['light' => '#2f6b3a', 'dark' => '#6cbf7b'], 'page' => ['light' => '#f6f8f5', 'dark' => '#10150f']], 'radius' => 'normal'],
        'sand' => ['colors' => ['primary' => ['light' => '#8a4b12', 'dark' => '#e0a15e'], 'accent' => ['light' => '#7a5a10', 'dark' => '#d6b25a'], 'page' => ['light' => '#f8f5ef', 'dark' => '#17140f']], 'radius' => 'rounded'],
        'graphite' => ['colors' => ['primary' => ['light' => '#374151', 'dark' => '#cbd5e1'], 'accent' => ['light' => '#1d4ed8', 'dark' => '#93b4f5']], 'radius' => 'square', 'density' => 'compact', 'shadow' => 'none'],
    ];

    /**
     * Normalizes what an admin sent: unknown keys dropped, colours as lowercase #rrggbb or null.
     *
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function normalize(array $t): array
    {
        $errors = [];
        $colors = [];
        foreach (self::COLORS as $c) {
            foreach (self::MODES as $m) {
                $v = $t['colors'][$c][$m] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                if (! Contrast::isHex($v)) {
                    $errors["tokens.colors.{$c}.{$m}"][] = __('experience.bad_colour');

                    continue;
                }
                $colors[$c][$m] = strtolower((string) $v);
            }
        }
        $out = ['colors' => (object) $colors];
        foreach (['radius' => self::RADIUS, 'density' => self::DENSITY, 'shadow' => self::SHADOW, 'font_latin' => self::FONTS, 'font_arabic' => self::FONTS] as $k => $allowed) {
            $v = $t[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            in_array($v, $allowed, true) ? $out[$k] = $v : $errors["tokens.{$k}"][] = __('experience.bad_choice');
        }
        $links = [];
        foreach (array_slice(array_values((array) ($t['legal_links'] ?? [])), 0, 8) as $i => $l) {
            $url = trim((string) ($l['url'] ?? ''));
            $label = [];
            foreach ((array) ($l['label'] ?? []) as $loc => $text) {
                if (is_string($loc) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $loc) === 1 && is_string($text) && trim($text) !== '') {
                    $label[$loc] = mb_substr(trim($text), 0, 120);
                }
            }
            if ($label === [] || preg_match('#^(https://[^\s/]+|/)[^\s]*$#', $url) !== 1 || str_starts_with($url, '//')) {
                $errors["tokens.legal_links.{$i}"][] = __('experience.bad_link');

                continue;
            }
            $links[] = ['url' => mb_substr($url, 0, 2048), 'label' => $label];
        }
        $out['legal_links'] = $links;
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * The effective colours per mode: the theme's, else the product's.
     *
     * @param  array<string, mixed>  $tokens
     * @return array<string, array<string, string>>
     */
    public static function resolved(array $tokens): array
    {
        $out = self::DEFAULTS;
        foreach (self::MODES as $m) {
            foreach (self::COLORS as $c) {
                $v = ((array) ($tokens['colors'] ?? []))[$c][$m] ?? null;
                if (is_string($v) && Contrast::isHex($v)) {
                    $out[$m][$c] = strtolower($v);
                }
            }
        }

        return $out;
    }

    /**
     * Every contrast check, in both modes.
     *
     * @param  array<string, mixed>  $tokens
     * @return list<array{check: string, mode: string, ratio: float, required: float, level: 'error'|'warning'|'ok', suggestion: string|null}>
     */
    public static function report(array $tokens): array
    {
        $out = [];
        foreach (self::resolved($tokens) as $mode => $c) {
            $add = static function (string $check, float $ratio, float $required, string $level, ?string $suggestion = null) use (&$out, $mode): void {
                $out[] = ['check' => $check, 'mode' => $mode, 'ratio' => round($ratio, 2), 'required' => $required,
                    'level' => $ratio >= $required ? 'ok' : $level, 'suggestion' => $ratio >= $required ? null : $suggestion];
            };
            $backgrounds = [$c['page'], $c['surface'], $c['subtle']];
            $lowest = static fn (string $fg, array $bgs): float => min(array_map(static fn (string $bg): float => Contrast::ratio($fg, $bg), $bgs));
            $add('text', $lowest($c['text'], $backgrounds), Contrast::AA_TEXT, 'error');
            $add('muted_text', $lowest($c['muted'], $backgrounds), Contrast::AA_TEXT, 'error');
            foreach (['primary', 'accent', 'success', 'warning', 'danger'] as $k) {
                $readOn = [$c['surface'], $c['subtle']];
                $add($k, $lowest($c[$k], $readOn), Contrast::AA_TEXT, 'error', Contrast::nearestPassing($c[$k], $mode, $readOn));
            }
            $add('border', Contrast::ratio($c['border'], $c['surface']), 1.12, 'warning');
            $add('surface_on_page', Contrast::ratio($c['surface'], $c['page']), 1.02, 'warning');
        }

        return $out;
    }

    /** @param  list<array<string, mixed>>  $report */
    public static function errors(array $report): int
    {
        return count(array_filter($report, static fn (array $r) => $r['level'] === 'error'));
    }

    /** @param  list<array<string, mixed>>  $report */
    public static function warnings(array $report): int
    {
        return count(array_filter($report, static fn (array $r) => $r['level'] === 'warning'));
    }
}
