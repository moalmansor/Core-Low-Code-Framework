<?php

declare(strict_types=1);

namespace App\Modules\Forms\Publishing;

/**
 * Structured diff between two definitions (architecture §13.3): per object
 * uuid, whether it was added, removed or changed, with before/after values of
 * every changed property path. Used for `form_versions.diff_from_previous`
 * and the visual diff screen.
 */
final class VersionDiff
{
    private const COLLECTIONS = ['groups', 'fields', 'relations', 'conditions'];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     * @return array{form: list<array<string, mixed>>, groups: list<array<string, mixed>>, fields: list<array<string, mixed>>, relations: list<array<string, mixed>>, conditions: list<array<string, mixed>>, access: array{added: int, removed: int, changed: int}, summary: array<string, int>}
     */
    public function diff(?array $before, array $after): array
    {
        $out = ['form' => $this->props($before['form'] ?? [], $after['form'], ['version', 'table'])];
        $summary = ['added' => 0, 'removed' => 0, 'changed' => 0];
        foreach (self::COLLECTIONS as $kind) {
            $a = $this->byUuid($before[$kind] ?? []);
            $b = $this->byUuid($after[$kind] ?? []);
            $entries = [];
            foreach ($b as $uuid => $obj) {
                if (! isset($a[$uuid])) {
                    $entries[] = ['uuid' => $uuid, 'key' => $obj['key'] ?? null, 'change' => 'added', 'after' => $obj];
                    $summary['added']++;

                    continue;
                }
                $changes = $this->props($a[$uuid], $obj);
                if ($changes !== []) {
                    $entries[] = ['uuid' => $uuid, 'key' => $obj['key'] ?? null, 'change' => 'changed', 'changes' => $changes];
                    $summary['changed']++;
                }
            }
            foreach ($a as $uuid => $obj) {
                if (! isset($b[$uuid])) {
                    $entries[] = ['uuid' => $uuid, 'key' => $obj['key'] ?? null, 'change' => 'removed', 'before' => $obj];
                    $summary['removed']++;
                }
            }
            $out[$kind] = $entries;
        }
        $accessKey = static fn (array $r): string => json_encode([$r['target'], $r['subject'], $r['status'] ?? null, $r['mode'] ?? null]);
        $ab = [];
        foreach ($before['access'] ?? [] as $r) {
            $ab[$accessKey($r)] = $r;
        }
        $aa = [];
        foreach ($after['access'] ?? [] as $r) {
            $aa[$accessKey($r)] = $r;
        }
        $out['access'] = [
            'added' => count(array_diff_key($aa, $ab)),
            'removed' => count(array_diff_key($ab, $aa)),
            'changed' => count(array_filter(array_intersect_key($aa, $ab), static fn (array $r, string $k) => ($ab[$k]['access'] ?? null) !== $r['access'] || ($ab[$k]['effect'] ?? null) !== $r['effect'], ARRAY_FILTER_USE_BOTH)),
        ];
        $out['summary'] = $summary;

        return $out;
    }

    /**
     * Changed property paths between two objects.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @param  list<string>  $ignore
     * @return list<array{path: string, before: mixed, after: mixed}>
     */
    public function props(array $a, array $b, array $ignore = [], string $prefix = ''): array
    {
        $out = [];
        $keys = array_unique([...array_keys($a), ...array_keys($b)]);
        foreach ($keys as $k) {
            if (in_array($k, $ignore, true)) {
                continue;
            }
            $va = $a[$k] ?? null;
            $vb = $b[$k] ?? null;
            $path = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($va) && is_array($vb) && ! array_is_list($va) && ! array_is_list($vb)) {
                array_push($out, ...$this->props($va, $vb, [], $path));
            } elseif ($this->normalize($va) !== $this->normalize($vb)) {
                $out[] = ['path' => $path, 'before' => $va, 'after' => $vb];
            }
        }

        return $out;
    }

    private function normalize(mixed $v): string
    {
        if (is_array($v) && $v === []) {
            return '[]';
        }

        return (string) json_encode($v);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function byUuid(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $out[$i['uuid']] = $i;
        }

        return $out;
    }
}
