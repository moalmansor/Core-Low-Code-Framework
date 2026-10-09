<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Numbers\Decimal;
use App\Expressions\Text\Unicode;
use App\Support\Html\HtmlSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Converts field values between the API shape, the physical columns
 * (architecture §11.3) and the stored form read back.
 *
 * API shapes: text → string; number/decimal → canonical decimal string;
 * boolean; date "YYYY-MM-DD"; time "HH:MM:SS"; datetime "YYYY-MM-DDTHH:MM:SSZ"
 * (UTC); duration → integer seconds; single choice → string; multi choice →
 * list of strings; references (lookup, pickers, collection-backed choices) →
 * record uuid or list of uuids; file → file uuid; files → list of uuids;
 * ranges → {from, to}; map → {lat, lng, label}; phone → {number, country};
 * currency → amount, or {amount, currency} when multi-currency; JSON and
 * key-value → JSON value.
 */
final class ValueCodec
{
    public const UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * Normalizes a submitted value to its API shape; null for empty input.
     *
     * @param  array<string, mixed>  $field
     *
     * @throws InvalidValue
     */
    public function normalize(FormRuntime $rt, array $field, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        $type = $rt->type($field);
        $storage = $type === null ? 'none' : $type->storage;
        $multiRef = $rt->isMultiReference($field);
        $ref = $rt->targetTable($field) !== null;

        return match ($storage) {
            'string', 'text', 'longtext', 'auto_number' => ($field['type'] ?? null) === 'rich_text' ? $this->sanitizer->clean($this->text($value, $field)) : $this->text($value, $field),
            'decimal', 'number', 'currency' => $storage === 'currency' && ($field['storage']['multiCurrency'] ?? false) ? $this->money($value) : $this->decimal($value),
            'int' => $this->integer($value),
            'bool', 'consent' => $this->boolean($value),
            'date' => $this->date($value, $field['type']),
            'time' => $this->time($value),
            'datetime' => $this->datetime($value),
            'duration' => (int) $this->integer($value),
            'json' => $this->json($value, $field['type']),
            'choice' => $ref ? $this->uuid($value) : $this->text($value, $field),
            'multi_choice' => $ref ? $this->uuidList($value) : $this->textList($value),
            'lookup', 'user', 'role', 'department' => $multiRef ? $this->uuidList($value) : $this->uuid($value),
            'file' => $this->uuid($value),
            'files' => $this->uuidList($value),
            'range_date' => $this->range($value, fn ($v) => $this->date($v, 'date')),
            'range_time' => $this->range($value, fn ($v) => $this->time($v)),
            'range_datetime' => $this->range($value, fn ($v) => $this->datetime($v)),
            'map' => $this->map($value),
            'phone' => $this->phone($value),
            'formula' => $value,
            default => null,
        };
    }

    /**
     * Column values of a normalized value. References must already be resolved
     * to ids (`$refIds` maps uuid => id for this field's target table).
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, int>  $refIds
     * @return array<string, mixed>
     */
    public function toColumns(FormRuntime $rt, array $field, mixed $value, array $refIds = []): array
    {
        $cols = $rt->columns[$field['uuid']] ?? [];
        if ($cols === []) {
            return [];
        }
        $main = $rt->column($field['uuid']);
        if ($main !== null && ($main['encrypted'] ?? false)) {
            $out = [$main['name'] => $value === null ? null : Crypt::encryptString((string) json_encode($value, JSON_UNESCAPED_UNICODE))];
            if (($bidx = $rt->column($field['uuid'], 'bidx')) !== null) {
                $out[$bidx['name']] = $value === null ? null : self::blindIndex($value);
            }

            return $out;
        }
        $storage = $rt->type($field)?->storage;
        $name = $main['name'] ?? null;

        return match ($storage) {
            'range_date', 'range_time', 'range_datetime' => [
                $rt->column($field['uuid'], 'from')['name'] => $this->db($value['from'] ?? null, $rt->column($field['uuid'], 'from')['type']),
                $rt->column($field['uuid'], 'to')['name'] => $this->db($value['to'] ?? null, $rt->column($field['uuid'], 'to')['type']),
            ],
            'map' => [
                $rt->column($field['uuid'], 'lat')['name'] => $value['lat'] ?? null,
                $rt->column($field['uuid'], 'lng')['name'] => $value['lng'] ?? null,
                $rt->column($field['uuid'], 'label')['name'] => $value['label'] ?? null,
            ],
            'phone' => [$name => $value['number'] ?? null, $rt->column($field['uuid'], 'country')['name'] => $value['country'] ?? null],
            'currency' => is_array($value)
                ? array_filter([$name => $value['amount'] ?? null, ($rt->column($field['uuid'], 'currency')['name'] ?? '') => $value['currency'] ?? null], static fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH)
                : [$name => $value],
            'consent' => [$name => $value === null ? null : ($value ? 1 : 0)],
            'choice', 'lookup', 'user', 'role', 'department', 'file' => $name === null ? [] : [$name => $value === null ? null : ($main['type'] === 'bigint' ? ($refIds[$value] ?? null) : $value)],
            'multi_choice', 'files' => $name === null ? [] : [$name => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE)],
            default => $name === null ? [] : [$name => $this->db($value, $main['type'])],
        };
    }

    /**
     * API value from a stored row. `$refs` maps id => uuid for reference columns.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $row  column => raw value
     * @param  array<int|string, string>  $refs
     */
    public function fromRow(FormRuntime $rt, array $field, array $row, array $refs = []): mixed
    {
        $main = $rt->column($field['uuid']);
        if ($main === null) {
            return null;
        }
        if ($main['encrypted'] ?? false) {
            $raw = $row[$main['name']] ?? null;
            if ($raw === null) {
                return null;
            }
            try {
                return json_decode(Crypt::decryptString((string) $raw), true);
            } catch (Throwable) {
                return null;
            }
        }
        $storage = $rt->type($field)?->storage;
        $get = fn (?string $part) => ($c = $rt->column($field['uuid'], $part)) === null ? null : $this->fromDb($row[$c['name']] ?? null, $c['type']);

        return match ($storage) {
            'range_date', 'range_time', 'range_datetime' => ($get('from') === null && $get('to') === null) ? null : ['from' => $get('from'), 'to' => $get('to')],
            'map' => $get('lat') === null ? null : ['lat' => $get('lat'), 'lng' => $get('lng'), 'label' => $get('label')],
            'phone' => $get(null) === null ? null : ['number' => $get(null), 'country' => $get('country')],
            'currency' => ($field['storage']['multiCurrency'] ?? false) ? ($get(null) === null ? null : ['amount' => $get(null), 'currency' => $get('currency')]) : $get(null),
            'choice', 'lookup', 'user', 'role', 'department', 'file' => $main['type'] === 'bigint'
                ? (($id = $row[$main['name']] ?? null) === null ? null : ($refs[(int) $id] ?? null))
                : $get(null),
            'multi_choice', 'files' => ($raw = $row[$main['name']] ?? null) === null ? null : (is_array($raw) ? $raw : json_decode((string) $raw, true)),
            'json' => ($raw = $row[$main['name']] ?? null) === null ? null : (is_array($raw) ? $raw : json_decode((string) $raw, true)),
            default => $get(null),
        };
    }

    public static function blindIndex(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : (string) json_encode($value);
        $normalized = mb_strtolower(Unicode::normalizeArabic(trim($text)));

        return hash_hmac('sha256', $normalized, 'bidx|'.config('app.key'));
    }

    /** Value for a column of a logical type. */
    private function db(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => $value ? 1 : 0,
            'datetime' => (new DateTimeImmutable((string) $value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => $value,
        };
    }

    private function fromDb(mixed $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'bool' => (bool) $raw,
            'decimal' => ($d = Decimal::parse((string) $raw)) === null ? (string) $raw : $d->toString(),
            'int', 'bigint', 'smallint' => (string) $raw,
            'date' => substr((string) $raw, 0, 10),
            'time' => substr((string) $raw, 0, 8),
            'datetime' => (new DateTimeImmutable((string) $raw, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'json' => is_string($raw) ? json_decode($raw, true) : $raw,
            default => (string) $raw,
        };
    }

    private function text(mixed $value, array $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new InvalidValue('records.invalid_value.text');
        }
        $s = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        if (! mb_check_encoding($s, 'UTF-8')) {
            throw new InvalidValue('records.invalid_value.text');
        }

        return Unicode::nfc($s);
    }

    /** @return list<string> */
    private function textList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidValue('records.invalid_value.list');
        }

        return array_values(array_unique(array_map(fn ($v) => $this->text($v, []), array_filter($value, static fn ($v) => $v !== null && $v !== ''))));
    }

    private function decimal(mixed $value): string
    {
        if (is_bool($value) || is_array($value)) {
            throw new InvalidValue('records.invalid_value.number');
        }
        $s = Unicode::asciiDigits(trim(str_replace([',', '٬'], '', (string) $value)));
        $s = str_replace('٫', '.', $s);
        $d = preg_match('/^-?\d+(\.\d+)?$/', $s) === 1 ? Decimal::parse($s) : null;
        if ($d === null) {
            throw new InvalidValue('records.invalid_value.number');
        }

        return $d->toString();
    }

    private function integer(mixed $value): string
    {
        $d = $this->decimal($value);
        if (str_contains($d, '.')) {
            throw new InvalidValue('records.invalid_value.integer');
        }

        return $d;
    }

    /** @return array{amount: string, currency: string|null} */
    private function money(mixed $value): array
    {
        if (! is_array($value)) {
            return ['amount' => $this->decimal($value), 'currency' => null];
        }
        $currency = $value['currency'] ?? null;
        if ($currency !== null && preg_match('/^[A-Z]{3}$/', (string) $currency) !== 1) {
            throw new InvalidValue('records.invalid_value.currency');
        }

        return ['amount' => $this->decimal($value['amount'] ?? null), 'currency' => $currency];
    }

    private function boolean(mixed $value): bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1', $value === 'true' => true,
            $value === false, $value === 0, $value === '0', $value === 'false' => false,
            default => throw new InvalidValue('records.invalid_value.boolean'),
        };
    }

    private function date(mixed $value, string $type): string
    {
        $s = Unicode::asciiDigits(trim((string) $value));
        if ($type === 'month' && preg_match('/^\d{4}-\d{2}$/', $s) === 1) {
            $s .= '-01';
        }
        if ($type === 'week' && preg_match('/^(\d{4})-W(\d{2})$/', $s, $m) === 1) {
            $d = (new DateTimeImmutable)->setISODate((int) $m[1], (int) $m[2]);

            return $d->format('Y-m-d');
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        if ($d === false || $d->format('Y-m-d') !== $s) {
            throw new InvalidValue('records.invalid_value.date');
        }

        return $s;
    }

    private function time(mixed $value): string
    {
        $s = Unicode::asciiDigits(trim((string) $value));
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $s, $m) !== 1) {
            throw new InvalidValue('records.invalid_value.time');
        }

        return sprintf('%s:%s:%s', $m[1], $m[2], $m[4] ?? '00');
    }

    private function datetime(mixed $value): string
    {
        $s = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $s) !== 1) {
            throw new InvalidValue('records.invalid_value.datetime');
        }
        try {
            return (new DateTimeImmutable($s))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            throw new InvalidValue('records.invalid_value.datetime');
        }
    }

    private function json(mixed $value, string $type): mixed
    {
        if ($type === 'key_value') {
            if (! is_array($value)) {
                throw new InvalidValue('records.invalid_value.key_value');
            }
            $out = [];
            foreach ($value as $k => $v) {
                if (! is_scalar($v) && $v !== null) {
                    throw new InvalidValue('records.invalid_value.key_value');
                }
                $out[(string) $k] = $v === null ? null : (string) $v;
            }

            return $out;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidValue('records.invalid_value.json');
            }

            return $decoded;
        }
        if (strlen((string) json_encode($value)) > 1_000_000) {
            throw new InvalidValue('records.invalid_value.json_size');
        }

        return $value;
    }

    private function uuid(mixed $value): string
    {
        if (is_array($value) && isset($value['uuid'])) {
            $value = $value['uuid'];
        }
        if (! is_string($value) || preg_match(self::UUID, $value) !== 1) {
            throw new InvalidValue('records.invalid_value.reference');
        }

        return strtolower($value);
    }

    /** @return list<string> */
    private function uuidList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidValue('records.invalid_value.reference');
        }

        return array_values(array_unique(array_map($this->uuid(...), $value)));
    }

    /** @return array{from: mixed, to: mixed} */
    private function range(mixed $value, callable $item): array
    {
        if (! is_array($value)) {
            throw new InvalidValue('records.invalid_value.range');
        }
        $from = ($value['from'] ?? null) === null || $value['from'] === '' ? null : $item($value['from']);
        $to = ($value['to'] ?? null) === null || $value['to'] === '' ? null : $item($value['to']);
        if ($from !== null && $to !== null && strcmp((string) $from, (string) $to) > 0) {
            throw new InvalidValue('records.invalid_value.range_order');
        }

        return ['from' => $from, 'to' => $to];
    }

    /** @return array{lat: string, lng: string, label: string|null} */
    private function map(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidValue('records.invalid_value.location');
        }
        $lat = $this->decimal($value['lat'] ?? null);
        $lng = $this->decimal($value['lng'] ?? null);
        if (abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            throw new InvalidValue('records.invalid_value.location');
        }
        $round = static function (string $v): string {
            $r = Decimal::parse($v)?->round(7);

            return $r instanceof Decimal ? $r->toString() : $v;
        };

        return ['lat' => $round($lat), 'lng' => $round($lng), 'label' => isset($value['label']) ? mb_substr((string) $value['label'], 0, 255) : null];
    }

    /** @return array{number: string, country: string|null} */
    private function phone(mixed $value): array
    {
        $number = is_array($value) ? ($value['number'] ?? '') : (string) $value;
        $number = preg_replace('/[\s()-]/', '', Unicode::asciiDigits((string) $number)) ?? '';
        if (preg_match('/^\+[1-9]\d{6,14}$/', $number) !== 1) {
            throw new InvalidValue('records.invalid_value.phone');
        }
        $country = is_array($value) ? ($value['country'] ?? null) : null;
        if ($country !== null && preg_match('/^[A-Z]{2}$/', (string) $country) !== 1) {
            throw new InvalidValue('records.invalid_value.phone');
        }

        return ['number' => $number, 'country' => $country];
    }
}
