<?php

declare(strict_types=1);

namespace App\Modules\DataQuality;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Support\Facades\DB;

/**
 * Finding duplicates with a form's duplicate rules (specification §4.35,
 * ADR-0044). A rule compares chosen fields, each exactly, normalized
 * (case, spacing, punctuation, Arabic letter forms, diacritics and digits
 * ignored) or fuzzily (similar text above a threshold), and matches when all
 * — or any — of its fields match. While a record is saved, exact fields are
 * looked up in the database and the rest compared against at most
 * `data_quality.duplicate_scan_limit` recent records; a sweep compares every
 * record of the form.
 */
final class DuplicateFinder
{
    /** Types whose values are text and can be compared normalized or fuzzily. */
    public const TEXT_STORAGE = ['string', 'text', 'phone'];

    /** Types whose values can be compared at all. */
    public const COMPARABLE = ['string', 'text', 'phone', 'number', 'int', 'decimal', 'date', 'datetime', 'choice', 'lookup', 'user', 'department', 'currency'];

    private const SWEEP_BLOCK = 400;

    public function __construct(private readonly RecordStore $store, private readonly SettingsService $settings) {}

    /** Text with what should not tell two values apart taken out. */
    public static function normalize(mixed $value): string
    {
        $s = mb_strtolower(trim((string) $value));
        // Arabic: diacritics and tatweel go; letter forms that are often typed interchangeably become one.
        $s = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        $s = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);

        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** How alike two texts are, from 0 to 1, by edit distance on their normalized letters. */
    public static function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return 1.0;
        }
        $x = mb_str_split(mb_substr($a, 0, 120));
        $y = mb_str_split(mb_substr($b, 0, 120));
        $n = count($x);
        $m = count($y);
        if ($n === 0 || $m === 0) {
            return 0.0;
        }
        $prev = range(0, $m);
        for ($i = 1; $i <= $n; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $m; $j++) {
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + ($x[$i - 1] === $y[$j - 1] ? 0 : 1));
            }
            $prev = $cur;
        }

        return 1 - $prev[$m] / max($n, $m);
    }

    /**
     * Records of the form that a rule says duplicate the given values.
     *
     * @param  array{fields: list<array{field: string, method: string, threshold: float|null}>, mode: string}  $rule
     * @param  array<string, mixed>  $values  the record's values, as the pipeline saves them
     * @return list<int> record ids, at most 10
     */
    public function find(FormRuntime $rt, array $rule, array $values, ?int $excludeId): array
    {
        $parts = $this->parts($rt, $rule, $values);
        if ($parts === null) {
            return [];
        }
        [$exact, $loose] = $parts;
        $all = $rule['mode'] === 'all';
        $found = [];
        if ($exact !== []) {
            $q = DB::table($rt->table)->whereNull('deleted_at')->when($excludeId !== null, static fn ($q) => $q->where('id', '!=', $excludeId));
            $q->where(static function ($w) use ($exact, $all): void {
                foreach ($exact as $col => $v) {
                    $all ? $w->where($col, $v) : $w->orWhere($col, $v);
                }
            });
            $candidates = $q->orderByDesc('id')->limit($all && $loose !== [] ? (int) $this->settings->get('data_quality', 'duplicate_scan_limit') : 10)
                ->get(['id', ...array_column($loose, 'column')]);
            foreach ($candidates as $c) {
                if (! $all || $this->looseMatch($loose, $c, true)) {
                    $found[] = (int) $c->id;
                }
            }
            if ($all || count($found) >= 10) {
                return array_slice($found, 0, 10);
            }
        }
        if ($loose === []) {
            return array_slice($found, 0, 10);
        }
        // Normalized and fuzzy fields: compared here, against the most recent records.
        $rows = DB::table($rt->table)->whereNull('deleted_at')->when($excludeId !== null, static fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderByDesc('id')->limit((int) $this->settings->get('data_quality', 'duplicate_scan_limit'))->get(['id', ...array_column($loose, 'column')]);
        foreach ($rows as $c) {
            if (! in_array((int) $c->id, $found, true) && $this->looseMatch($loose, $c, $all)) {
                $found[] = (int) $c->id;
                if (count($found) >= 10) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Groups of duplicates among all live records of the form (a sweep).
     *
     * @param  array{fields: list<array{field: string, method: string, threshold: float|null}>, mode: string}  $rule
     * @return list<list<int>> record ids, each group of two or more
     */
    public function sweep(FormRuntime $rt, array $rule): array
    {
        $fields = [];
        foreach ($rule['fields'] as $m) {
            $f = $rt->fields[$m['field']] ?? null;
            $col = $f === null ? null : ($rt->column($f['uuid'])['name'] ?? null);
            if ($col !== null) {
                $fields[] = ['column' => $col, 'method' => $m['method'], 'threshold' => (float) ($m['threshold'] ?? 0.85)];
            }
        }
        if ($fields === []) {
            return [];
        }
        $parent = [];
        $find = static function (int $x) use (&$parent): int {
            while (($parent[$x] ?? $x) !== $x) {
                $parent[$x] = $parent[$parent[$x]] ?? $parent[$x];
                $x = $parent[$x];
            }

            return $x;
        };
        $union = static function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[max($ra, $rb)] = min($ra, $rb);
            }
        };
        $rows = DB::table($rt->table)->whereNull('deleted_at')->orderBy('id')->get(['id', ...array_column($fields, 'column')]);
        $all = $rule['mode'] === 'all';
        if ($all && array_filter($fields, static fn ($f) => $f['method'] === 'fuzzy') === []) {
            // Every field exact or normalized: records with the same combined key are duplicates.
            $groups = [];
            foreach ($rows as $r) {
                $key = $this->key($fields, $r);
                if ($key !== null) {
                    $groups[$key][] = (int) $r->id;
                }
            }
            foreach ($groups as $ids) {
                for ($i = 1; $i < count($ids); $i++) {
                    $union($ids[0], $ids[$i]);
                }
            }
        } elseif ($all) {
            // A fuzzy field among all: compare pairs within blocks sharing an exact or normalized field, else the start of the first field.
            $keyed = array_values(array_filter($fields, static fn ($f) => $f['method'] !== 'fuzzy'))[0] ?? null;
            $blocks = [];
            foreach ($rows as $r) {
                $v = (string) ($r->{($keyed ?? $fields[0])['column']} ?? '');
                if (trim($v) !== '') {
                    $blocks[$keyed === null ? mb_substr(self::normalize($v), 0, 2) : ($keyed['method'] === 'normalized' ? self::normalize($v) : $v)][] = $r;
                }
            }
            $this->comparePairs($blocks, $fields, true, $union);
        } else {
            // Any field: records sharing a key of an exact or normalized field are joined; fuzzy fields compare within blocks.
            foreach ($fields as $f) {
                $groups = [];
                foreach ($rows as $r) {
                    $v = (string) ($r->{$f['column']} ?? '');
                    if (trim($v) !== '') {
                        $groups[$f['method'] === 'fuzzy' ? mb_substr(self::normalize($v), 0, 2) : ($f['method'] === 'normalized' ? self::normalize($v) : $v)][] = $r;
                    }
                }
                if ($f['method'] === 'fuzzy') {
                    $this->comparePairs($groups, [$f], true, $union);

                    continue;
                }
                foreach ($groups as $members) {
                    for ($i = 1; $i < count($members); $i++) {
                        $union((int) $members[0]->id, (int) $members[$i]->id);
                    }
                }
            }
        }
        $out = [];
        foreach (array_keys($parent) as $id) {
            $out[$find($id)][] = $id;
        }
        foreach ($out as $root => $ids) {
            $out[$root] = array_values(array_unique([$root, ...$ids]));
            sort($out[$root]);
        }

        return array_values(array_filter($out, static fn ($g) => count($g) > 1));
    }

    /**
     * The exact parts (column => stored value) and the rest of a rule for
     * the given values; null when the rule cannot match (an "all" rule with an
     * empty field).
     *
     * @param  array{fields: list<array{field: string, method: string, threshold: float|null}>, mode: string}  $rule
     * @param  array<string, mixed>  $values
     * @return array{0: array<string, mixed>, 1: list<array{column: string, method: string, threshold: float, value: string}>}|null
     */
    private function parts(FormRuntime $rt, array $rule, array $values): ?array
    {
        $exact = [];
        $loose = [];
        foreach ($rule['fields'] as $m) {
            $f = $rt->fields[$m['field']] ?? null;
            $col = $f === null ? null : ($rt->column($f['uuid'])['name'] ?? null);
            $stored = $f === null || $col === null ? null : ($this->store->columns($rt, [$f], $values)[$col] ?? null);
            if ($stored === null || (is_string($stored) && trim($stored) === '')) {
                if ($rule['mode'] === 'all') {
                    return null;
                }

                continue;
            }
            if ($m['method'] === 'exact' || ! in_array($rt->type($f)?->storage, self::TEXT_STORAGE, true)) {
                $exact[$col] = $stored;
            } else {
                $loose[] = ['column' => $col, 'method' => $m['method'], 'threshold' => (float) ($m['threshold'] ?? 0.85), 'value' => (string) $stored];
            }
        }

        return $exact === [] && $loose === [] ? null : [$exact, $loose];
    }

    /**
     * Compares the records of each block pairwise (in chunks, so a very common
     * value stays affordable) and joins those that match.
     *
     * @param  array<string, list<object>>  $blocks
     * @param  list<array{column: string, method: string, threshold: float}>  $fields
     * @param  callable(int, int): void  $union
     */
    private function comparePairs(array $blocks, array $fields, bool $all, callable $union): void
    {
        foreach ($blocks as $block) {
            foreach (array_chunk($block, self::SWEEP_BLOCK) as $chunk) {
                $n = count($chunk);
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        if ($this->pairMatch($fields, $chunk[$i], $chunk[$j], $all)) {
                            $union((int) $chunk[$i]->id, (int) $chunk[$j]->id);
                        }
                    }
                }
            }
        }
    }

    /** @param  list<array{column: string, method: string, threshold: float, value: string}>  $loose */
    private function looseMatch(array $loose, object $row, bool $all): bool
    {
        foreach ($loose as $l) {
            $other = (string) ($row->{$l['column']} ?? '');
            $hit = $other !== '' && ($l['method'] === 'fuzzy' ? self::similarity($l['value'], $other) >= $l['threshold'] : self::normalize($l['value']) === self::normalize($other));
            if ($all && ! $hit) {
                return false;
            }
            if (! $all && $hit) {
                return true;
            }
        }

        return $all;
    }

    /** @param  list<array{column: string, method: string, threshold: float}>  $fields */
    private function pairMatch(array $fields, object $a, object $b, bool $all): bool
    {
        foreach ($fields as $f) {
            $x = (string) ($a->{$f['column']} ?? '');
            $y = (string) ($b->{$f['column']} ?? '');
            $hit = $x !== '' && $y !== '' && match ($f['method']) {
                'fuzzy' => self::similarity($x, $y) >= $f['threshold'],
                'normalized' => self::normalize($x) === self::normalize($y),
                default => $x === $y,
            };
            if ($all && ! $hit) {
                return false;
            }
            if (! $all && $hit) {
                return true;
            }
        }

        return $all;
    }

    /** @param  list<array{column: string, method: string, threshold: float}>  $fields */
    private function key(array $fields, object $row): ?string
    {
        $parts = [];
        foreach ($fields as $f) {
            $v = (string) ($row->{$f['column']} ?? '');
            if (trim($v) === '') {
                return null;
            }
            $parts[] = $f['method'] === 'normalized' ? self::normalize($v) : $v;
        }

        return implode("\x1F", $parts);
    }
}
