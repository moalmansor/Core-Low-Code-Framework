<?php

declare(strict_types=1);

namespace App\Modules\Records\Templating;

use App\Modules\Core\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * How a template shows one value (specification §4.17): a placeholder may
 * carry formats after a bar, `{{ due | date:long }}`,
 * `{{ amount | currency:SAR | digits:arabic }}`. The formats are a fixed
 * list, never code:
 *
 * - `date:short|medium|long|iso` and `datetime:short|medium|long|iso`;
 * - `number:0`…`number:6` (decimal places, with the system's separators);
 * - `currency:CODE` (a currency of Reference data: its decimals and symbol);
 * - `digits:arabic|western` (Arabic-Indic or Western digits).
 *
 * A value the reader may not see stays empty whatever its format: formats
 * apply only to what would have been shown.
 */
final class ValueFormat
{
    public const DATE_STYLES = ['short', 'medium', 'long', 'iso'];

    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * The formats of a spec (`date:long|digits:arabic`), or null when any is
     * unknown. An empty spec is no format.
     *
     * @return list<array{0: string, 1: string}>|null
     */
    public static function parse(string $spec): ?array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode('|', $spec)), static fn (string $p) => $p !== '') as $part) {
            [$name, $arg] = array_pad(array_map('trim', explode(':', $part, 2)), 2, '');
            $ok = match ($name) {
                'date', 'datetime' => in_array($arg, self::DATE_STYLES, true),
                'number' => preg_match('/^[0-6]$/', $arg) === 1,
                'currency' => preg_match('/^[A-Z]{3}$/', $arg) === 1,
                'digits' => in_array($arg, ['arabic', 'western'], true),
                default => false,
            };
            if (! $ok) {
                return null;
            }
            $out[] = [$name, $arg];
        }

        return $out;
    }

    /** A spec written back in one form: `date:long|digits:arabic`. @param  list<array{0: string, 1: string}>  $formats */
    public static function spec(array $formats): string
    {
        return implode('|', array_map(static fn (array $f) => $f[0].':'.$f[1], $formats));
    }

    /**
     * A value with its formats applied. $display is what the reader would see
     * without them; $raw is the stored value it came from.
     *
     * @param  list<array{0: string, 1: string}>  $formats
     */
    public function apply(mixed $raw, string $display, array $formats, string $locale): string
    {
        if ($display === '' || $formats === []) {
            return $display;
        }
        $text = $display;
        $value = is_array($raw) ? ($raw[0] ?? null) : $raw;
        foreach ($formats as [$name, $arg]) {
            $text = match ($name) {
                'date', 'datetime' => $this->date($value, $name === 'datetime', $arg, $locale) ?? $text,
                'number' => is_numeric($value) ? $this->number((float) $value, (int) $arg) : $text,
                'currency' => is_numeric($value) ? $this->currency((float) $value, $arg, $locale) : $text,
                'digits' => self::digits($text, $arg),
                default => $text,
            };
        }

        return $text;
    }

    /** Digits of a text as Arabic-Indic or Western ones. */
    public static function digits(string $text, string $style): string
    {
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        if ($style === 'arabic') {
            // Separators between digits take their Arabic forms too.
            $text = (string) preg_replace(['/(?<=\d)\.(?=\d)/u', '/(?<=\d),(?=\d)/u'], ['٫', '٬'], $text);

            return str_replace($western, self::ARABIC, $text);
        }

        return str_replace([...self::ARABIC, '٫', '٬'], [...$western, '.', ','], $text);
    }

    private function date(mixed $value, bool $withTime, string $style, string $locale): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            $at = strlen($value) <= 10 ? Carbon::parse($value) : Carbon::parse($value, 'UTC')->setTimezone((string) $this->settings->get('formats', 'timezone'));
        } catch (Throwable) {
            return null;
        }
        $at = $at->locale($locale);
        $time = $withTime ? ' '.$at->format($this->settings->get('formats', 'time_format') === '12h' ? 'g:i A' : 'H:i') : '';

        return match ($style) {
            'iso' => $at->format($withTime ? 'Y-m-d\TH:i' : 'Y-m-d'),
            'short' => $at->format('d/m/Y').$time,
            'medium' => $at->translatedFormat('j M Y').$time,
            default => $at->translatedFormat('j F Y').$time,
        };
    }

    private function number(float $value, int $decimals): string
    {
        $decimal = (string) $this->settings->get('formats', 'decimal_separator');
        $thousands = (string) ($this->settings->get('formats', 'thousands_separator') ?? '');

        return number_format($value, $decimals, $decimal, $thousands);
    }

    private function currency(float $value, string $code, string $locale): string
    {
        $c = DB::table('currencies')->where('code', $code)->first(['symbol', 'decimals', 'symbol_position']);
        $amount = $this->number($value, $c === null ? 2 : (int) $c->decimals);
        $symbol = $c === null || trim((string) $c->symbol) === '' ? $code : (string) $c->symbol;

        return ($c->symbol_position ?? 'after') === 'before' && ! in_array($locale, ['ar', 'fa', 'he', 'ur'], true) ? $symbol.' '.$amount : $amount.' '.$symbol;
    }
}
