<?php

declare(strict_types=1);

namespace App\Modules\Reference;

use App\Expressions\Calendars\Civil;
use App\Expressions\Calendars\UmmAlQura;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Reference\Models\NumberSequence;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Numbering sequences (specification §4.34, architecture §19.19): the next
 * number is taken under a row lock inside the caller's transaction, the
 * counter resets when the period bucket changes, and the pattern renders the
 * prefix, date parts (Gregorian or Hijri) and the padded sequence.
 *
 * Pattern tokens: {prefix} {yyyy} {yy} {MM} {dd} {seq} {seq:N} (N = padding).
 */
final class NumberGenerator
{
    public const TOKENS = '/\{(prefix|yyyy|yy|MM|dd|seq(?::(\d{1,2}))?)\}/';

    public function __construct(private readonly DatabaseDriver $driver) {}

    public function next(NumberSequence $sequence, ?DateTimeImmutable $at = null): string
    {
        return DB::transaction(function () use ($sequence, $at): string {
            $row = $this->driver->lockForUpdate(DB::table('number_sequences')->where('id', $sequence->id))->first();
            if ($row === null) {
                throw new InvalidArgumentException('Unknown sequence.');
            }
            $at ??= new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC')));
            $parts = $this->dateParts((string) $row->calendar, $at);
            $period = self::period((string) $row->reset_period, $parts);
            $value = $period === (string) $row->period_key ? (int) $row->current_value + (int) $row->step : (int) $row->step;
            DB::table('number_sequences')->where('id', $sequence->id)->update(['current_value' => $value, 'period_key' => $period]);

            return $this->render((string) $row->pattern, (string) ($row->prefix ?? ''), (int) $row->padding, $parts, $value);
        });
    }

    /** What the next number would look like, without consuming it. */
    public function preview(NumberSequence $sequence, ?DateTimeImmutable $at = null): string
    {
        $at ??= new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC')));
        $parts = $this->dateParts($sequence->calendar, $at);
        $value = self::period($sequence->reset_period, $parts) === $sequence->period_key ? $sequence->current_value + $sequence->step : $sequence->step;

        return $this->render($sequence->pattern, (string) $sequence->prefix, $sequence->padding, $parts, $value);
    }

    /** @param  array{yyyy: string, yy: string, MM: string, dd: string}  $parts */
    private static function period(string $reset, array $parts): string
    {
        return match ($reset) {
            'daily' => $parts['yyyy'].$parts['MM'].$parts['dd'],
            'monthly' => $parts['yyyy'].$parts['MM'],
            'yearly' => $parts['yyyy'],
            default => 'all',
        };
    }

    public static function validPattern(string $pattern): bool
    {
        return str_contains($pattern, '{seq') && preg_replace(self::TOKENS, '', $pattern) !== null
            && preg_match('/[{}]/', (string) preg_replace(self::TOKENS, '', $pattern)) !== 1
            && mb_strlen($pattern) <= 255;
    }

    /** @return array{yyyy: string, yy: string, MM: string, dd: string} */
    private function dateParts(string $calendar, DateTimeImmutable $at): array
    {
        if ($calendar === 'hijri') {
            $days = (int) Civil::parseDate($at->format('Y-m-d'));
            $h = UmmAlQura::fromDays($days);
            if ($h !== null) {
                return ['yyyy' => sprintf('%04d', $h[0]), 'yy' => sprintf('%02d', $h[0] % 100), 'MM' => sprintf('%02d', $h[1]), 'dd' => sprintf('%02d', $h[2])];
            }
        }

        return ['yyyy' => $at->format('Y'), 'yy' => $at->format('y'), 'MM' => $at->format('m'), 'dd' => $at->format('d')];
    }

    /** @param  array{yyyy: string, yy: string, MM: string, dd: string}  $parts */
    private function render(string $pattern, string $prefix, int $padding, array $parts, int $value): string
    {
        return (string) preg_replace_callback(self::TOKENS, static function (array $m) use ($prefix, $padding, $parts, $value): string {
            return match (true) {
                $m[1] === 'prefix' => $prefix,
                str_starts_with($m[1], 'seq') => str_pad((string) $value, ($m[2] ?? '') === '' ? $padding : (int) $m[2], '0', STR_PAD_LEFT),
                default => $parts[$m[1]],
            };
        }, $pattern);
    }
}
