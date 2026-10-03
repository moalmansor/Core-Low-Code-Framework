<?php

declare(strict_types=1);

namespace App\Expressions\Evaluation;

/**
 * Structural helpers over the JSON AST (expression-language.md §6).
 */
final class Ast
{
    /**
     * Depth (nodes on the longest root-to-leaf path) and total node count.
     *
     * @param  array<string, mixed>  $node
     * @return array{0: int, 1: int}
     */
    public static function measure(array $node): array
    {
        $children = self::children($node);
        $depth = 0;
        $nodes = 1;
        foreach ($children as $child) {
            [$d, $n] = self::measure($child);
            $depth = max($depth, $d);
            $nodes += $n;
        }

        return [$depth + 1, $nodes];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    public static function children(array $node): array
    {
        $out = [];
        $candidates = match ($node['k'] ?? null) {
            'list' => $node['items'] ?? [],
            'call' => $node['args'] ?? [],
            'un' => [$node['a'] ?? null],
            'bin' => [$node['a'] ?? null, $node['b'] ?? null],
            default => [],
        };
        foreach (is_array($candidates) ? $candidates : [] as $child) {
            if (is_array($child)) {
                $out[] = $child;
            }
        }

        return $out;
    }

    /**
     * Canonical JSON (keys sorted recursively, no escaping of slashes/Unicode),
     * used for parser parity and for storage.
     *
     * @param  array<string, mixed>|list<mixed>  $ast
     */
    public static function canonicalJson(array $ast): string
    {
        return (string) json_encode(self::sortKeys($ast), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::sortKeys(...), $value);
        }
        ksort($value, SORT_STRING);

        return array_map(self::sortKeys(...), $value);
    }

    /**
     * Every reference node with its node path, in evaluation order.
     *
     * @param  array<string, mixed>  $node
     * @return list<array{scope: string, path: list<string>, node: string}>
     */
    public static function references(array $node, string $path = ''): array
    {
        $refs = [];
        if (($node['k'] ?? null) === 'ref') {
            $refs[] = ['scope' => (string) ($node['scope'] ?? 'record'), 'path' => array_values($node['path'] ?? []), 'node' => ltrim($path, '.')];
        }
        $key = match ($node['k'] ?? null) {
            'list' => 'items',
            'call' => 'args',
            default => null,
        };
        if ($key !== null) {
            foreach ($node[$key] ?? [] as $i => $child) {
                $refs = [...$refs, ...self::references($child, $path.'.'.$key.'.'.$i)];
            }
        } elseif (in_array($node['k'] ?? null, ['un', 'bin'], true)) {
            foreach (['a', 'b'] as $side) {
                if (isset($node[$side]) && is_array($node[$side])) {
                    $refs = [...$refs, ...self::references($node[$side], $path.'.'.$side)];
                }
            }
        }

        return $refs;
    }
}
