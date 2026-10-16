<?php

declare(strict_types=1);

namespace App\Modules\Records\Exchange;

use App\Expressions\Text\Unicode;
use App\Modules\Core\I18n\Translator;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\InvalidValue;
use App\Modules\Records\Runtime\References;
use App\Modules\Records\Runtime\ValueCodec;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Converts record values to spreadsheet cells and back (specification §4.8
 * "Excel import/export"). Cells are human-readable: choices show their label,
 * references the referenced record's title, booleans Yes/No. Import accepts
 * what export produces, plus stable codes: option values, record uuids,
 * e-mail addresses for users, keys for roles and codes for departments.
 * Lists are separated by ";", ranges by " / ".
 */
final class SheetCodec
{
    public const LIST_SEPARATOR = '; ';

    /** @var array<string, array<string, list<string>>> field uuid => normalized title => uuids */
    private array $titleIndex = [];

    public function __construct(private readonly References $refs, private readonly Translator $translator) {}

    /** Field types whose values cannot travel through a spreadsheet. */
    public function importable(FormRuntime $rt, array $field): bool
    {
        $storage = $rt->type($field)?->storage;

        return $rt->isStored($field) && ! in_array($storage, ['file', 'files', 'json', 'formula', 'auto_number'], true)
            && ($field['export']['importable'] ?? true) !== false && ! ($field['behavior']['calculated'] ?? false);
    }

    public function exportable(FormRuntime $rt, array $field): bool
    {
        return $rt->isStored($field) && ($field['export']['exportable'] ?? true) !== false;
    }

    /** Column header: the field's Excel column name, else its column label, else its label, in the reader's language (else its key made readable). */
    public function header(array $field): string
    {
        $i18n = $field['i18n'] ?? [];
        if (($field['export']['excelColumn'] ?? null) !== null && $field['export']['excelColumn'] !== '') {
            return (string) $field['export']['excelColumn'];
        }

        return $this->localized($i18n['columnLabel'] ?? []) ?? $this->localized($i18n['label'] ?? []) ?? Translator::humanize((string) $field['key']);
    }

    /**
     * Every header text that identifies the field on import, normalized.
     *
     * @return list<string>
     */
    public function aliases(array $field): array
    {
        $out = [self::norm((string) $field['key'])];
        if (($field['export']['excelColumn'] ?? '') !== '') {
            $out[] = self::norm((string) $field['export']['excelColumn']);
        }
        foreach (['label', 'columnLabel'] as $k) {
            foreach ($field['i18n'][$k] ?? [] as $text) {
                if (is_string($text) && $text !== '') {
                    $out[] = self::norm($text);
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Spreadsheet value of an API value.
     *
     * @param  array<string, string>  $titles  referenced uuid => title
     * @param  array<string, string>  $fileNames  file uuid => name
     */
    public function toCell(FormRuntime $rt, array $field, mixed $value, array $titles, array $fileNames): bool|int|float|string|null
    {
        if ($value === null) {
            return null;
        }
        $storage = $rt->type($field)?->storage;
        $ref = $rt->targetTable($field) !== null;

        return match (true) {
            $ref && is_array($value) => implode(self::LIST_SEPARATOR, array_map(static fn ($u) => $titles[$u] ?? $u, $value)),
            $ref => $titles[$value] ?? (string) $value,
            in_array($storage, ['bool', 'consent'], true) => (bool) $value,
            $storage === 'choice' => $this->optionLabel($field, (string) $value),
            $storage === 'multi_choice' => implode(self::LIST_SEPARATOR, array_map(fn ($v) => $this->optionLabel($field, (string) $v), (array) $value)),
            in_array($storage, ['decimal', 'number', 'currency'], true) => is_array($value)
                ? trim(($value['amount'] ?? '').' '.($value['currency'] ?? ''))
                : self::number((string) $value),
            in_array($storage, ['int', 'duration'], true) => self::number((string) $value),
            in_array($storage, ['range_date', 'range_time', 'range_datetime'], true) => ($value['from'] ?? '').' / '.($value['to'] ?? ''),
            $storage === 'map' => ($value['lat'] ?? '').','.($value['lng'] ?? '').(($value['label'] ?? null) ? ' '.$value['label'] : ''),
            $storage === 'phone' => (string) ($value['number'] ?? ''),
            in_array($storage, ['file', 'files'], true) => implode(self::LIST_SEPARATOR, array_map(static fn ($u) => $fileNames[$u] ?? $u, (array) $value)),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    /**
     * API input of a spreadsheet cell; null for an empty cell.
     *
     * @throws InvalidValue
     */
    public function fromCell(FormRuntime $rt, array $field, mixed $cell): mixed
    {
        if ($cell === null || (is_string($cell) && trim($cell) === '')) {
            return null;
        }
        $storage = $rt->type($field)?->storage;
        if ($cell instanceof DateTimeInterface) {
            $cell = match ($storage) {
                'date', 'range_date' => $cell->format('Y-m-d'),
                'time', 'range_time' => $cell->format('H:i:s'),
                default => DateTimeImmutable::createFromInterface($cell)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            };
        }
        if (is_float($cell)) {
            $cell = self::floatText($cell);
        }
        if ($rt->targetTable($field) !== null) {
            $items = $this->split($cell);
            $uuids = array_map(fn (string $t) => $this->resolveReference($rt, $field, $t), $items);

            return $rt->isMultiReference($field) || $storage === 'multi_choice' ? $uuids : ($uuids[0] ?? null);
        }

        return match ($storage) {
            'bool', 'consent' => self::boolean($cell),
            'choice' => $this->optionValue($field, Unicode::trim((string) $cell)),
            'multi_choice' => array_map(fn (string $v) => $this->optionValue($field, $v), $this->split($cell)),
            'currency' => ($field['storage']['multiCurrency'] ?? false) && is_string($cell) && preg_match('/^\s*(\S+)\s+([A-Za-z]{3})\s*$/', $cell, $m) === 1
                ? ['amount' => $m[1], 'currency' => strtoupper($m[2])]
                : (is_string($cell) ? Unicode::trim($cell) : $cell),
            'range_date', 'range_time', 'range_datetime' => self::range((string) $cell),
            'map' => self::location((string) $cell),
            'phone' => ['number' => (string) $cell, 'country' => null],
            default => is_bool($cell) ? ($cell ? '1' : '0') : $cell,
        };
    }

    public static function norm(string $text): string
    {
        return mb_strtolower(Unicode::normalizeArabic(Unicode::trim((string) preg_replace('/\s+/u', ' ', $text))));
    }

    /** @return list<string> */
    private function split(mixed $cell): array
    {
        return array_values(array_filter(array_map(static fn ($s) => Unicode::trim($s), explode(';', (string) $cell)), static fn ($s) => $s !== ''));
    }

    private function optionLabel(array $field, string $value): string
    {
        foreach ($field['options']['static'] ?? [] as $o) {
            if ($o['value'] === $value) {
                return $this->localized($o['i18n']['label'] ?? []) ?? $value;
            }
        }

        return $value;
    }

    /** Option value for a cell: the value itself, else a label in any language. Unknown text is returned unchanged for the validator to reject. */
    private function optionValue(array $field, string $text): string
    {
        $options = $field['options']['static'] ?? [];
        foreach ($options as $o) {
            if ($o['value'] === $text) {
                return $text;
            }
        }
        $wanted = self::norm($text);
        foreach ($options as $o) {
            if (self::norm($o['value']) === $wanted) {
                return $o['value'];
            }
            foreach ($o['i18n']['label'] ?? [] as $label) {
                if (is_string($label) && self::norm($label) === $wanted) {
                    return $o['value'];
                }
            }
        }

        return $text;
    }

    /** @throws InvalidValue */
    private function resolveReference(FormRuntime $rt, array $field, string $text): string
    {
        if (preg_match(ValueCodec::UUID, $text) === 1) {
            return strtolower($text);
        }
        $index = $this->titleIndex[$field['uuid']] ??= $this->buildIndex($rt, $field);
        $matches = array_values(array_unique($index[self::norm($text)] ?? []));
        if (count($matches) === 1) {
            return $matches[0];
        }
        throw new InvalidValue($matches === [] ? 'records.import.reference_not_found' : 'records.import.reference_ambiguous', ['value' => $text]);
    }

    /**
     * Normalized title → uuids of the target table, built once per field and
     * import. Users match by e-mail or name, roles by key or name, departments
     * by code or name, records by their display field.
     *
     * @return array<string, list<string>>
     */
    private function buildIndex(FormRuntime $rt, array $field): array
    {
        $table = (string) $rt->targetTable($field);
        $storage = $rt->type($field)?->storage;
        $index = [];
        $add = static function (mixed $title, string $uuid) use (&$index): void {
            if (is_string($title) && $title !== '') {
                $index[self::norm($title)][] = strtolower($uuid);
            }
        };
        if ($storage === 'user') {
            foreach (DB::table('users')->whereNull('deleted_at')->get(['uuid', 'email', 'name']) as $u) {
                $add($u->email, (string) $u->uuid);
                $add($u->name, (string) $u->uuid);
            }

            return $index;
        }
        if ($storage === 'role' || $storage === 'department') {
            $codeColumn = $storage === 'role' ? 'key' : 'code';
            $q = DB::table($table);
            if ($storage === 'department') {
                $q->whereNull('deleted_at');
            }
            $rows = $q->get(['id', 'uuid', $codeColumn]);
            $names = DB::table('translations')->where('object_type', $storage)->where('field', 'name')
                ->whereIn('object_id', $rows->pluck('id')->all())->get(['object_id', 'value']);
            $uuidById = $rows->pluck('uuid', 'id')->all();
            foreach ($rows as $r) {
                $add($r->{$codeColumn}, (string) $r->uuid);
            }
            foreach ($names as $n) {
                $add($n->value, (string) $uuidById[$n->object_id]);
            }

            return $index;
        }
        $column = $this->refs->displayColumn($rt, $field);
        foreach (DB::table($table)->whereNull('deleted_at')->orderBy('id')->lazy(2000) as $r) {
            $r = (array) $r;
            $add($r['record_number'] ?? null, (string) $r['uuid']);
            if ($column !== null) {
                $add(is_scalar($r[$column] ?? null) ? (string) $r[$column] : null, (string) $r['uuid']);
            }
        }

        return $index;
    }

    /** @param  array<string, string>  $values  locale => text */
    private function localized(array $values): ?string
    {
        foreach ($this->translator->fallbackChain() as $code) {
            if (($values[$code] ?? '') !== '') {
                return $values[$code];
            }
        }

        return null;
    }

    private static function number(string $value): int|float|string
    {
        if (preg_match('/^-?\d+$/', $value) === 1 && strlen(ltrim($value, '-')) <= 15) {
            return (int) $value;
        }
        if (preg_match('/^-?\d+\.\d+$/', $value) === 1 && strlen(str_replace(['-', '.'], '', $value)) <= 15) {
            return (float) $value;
        }

        return $value;
    }

    private static function floatText(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    private static function boolean(mixed $cell): bool
    {
        if (is_bool($cell)) {
            return $cell;
        }
        $text = self::norm((string) $cell);

        return match (true) {
            in_array($text, ['1', 'true', 'yes', 'y', 'نعم', 'صح'], true) => true,
            in_array($text, ['0', 'false', 'no', 'n', 'لا', 'خطا'], true) => false,
            default => throw new InvalidValue('records.invalid_value.boolean'),
        };
    }

    /** @return array{from: string|null, to: string|null} */
    private static function range(string $cell): array
    {
        $parts = array_map(static fn ($s) => Unicode::trim($s), explode('/', $cell, 2));
        if (count($parts) !== 2) {
            throw new InvalidValue('records.invalid_value.range');
        }

        return ['from' => $parts[0] === '' ? null : $parts[0], 'to' => $parts[1] === '' ? null : $parts[1]];
    }

    /** @return array{lat: string, lng: string, label: string|null} */
    private static function location(string $cell): array
    {
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*(.*)$/u', $cell, $m) !== 1) {
            throw new InvalidValue('records.invalid_value.location');
        }

        return ['lat' => $m[1], 'lng' => $m[2], 'label' => Unicode::trim($m[3]) === '' ? null : Unicode::trim($m[3])];
    }
}
