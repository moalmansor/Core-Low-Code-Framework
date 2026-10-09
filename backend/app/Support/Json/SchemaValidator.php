<?php

declare(strict_types=1);

namespace App\Support\Json;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Validates metadata JSON against the JSON Schema (draft 2020-12) documents of
 * architecture §14. Schemas are registered by `$id` from every
 * `app/Modules/*\/Schemas/*.schema.json` file and the expression AST schema,
 * so documents can `$ref` each other.
 *
 * PHP arrays cannot tell `{}` from `[]`; before validation, empty arrays at
 * positions where the schema expects an object are turned into objects
 * (`coerce()`), so the same document validates whether it came from JSON or
 * from PHP code.
 */
final class SchemaValidator
{
    private ?Validator $validator = null;

    /** @var array<string, array<string, mixed>> raw schemas by */
    private array $raw = [];

    /**
     * @return array<string, list<string>> JSON pointer => messages; empty when valid
     */
    public function errors(string $schemaId, mixed $document): array
    {
        $validator = $this->validator();
        $data = json_decode((string) json_encode($document, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), false, 512, JSON_THROW_ON_ERROR);
        $data = $this->coerce($data, $this->raw[$schemaId] ?? [], $this->raw[$schemaId] ?? []);
        $result = $validator->validate($data, $schemaId);
        if ($result->isValid()) {
            return [];
        }
        $out = [];
        foreach ((new ErrorFormatter)->format($result->error(), true) as $pointer => $messages) {
            $out[$pointer === '' ? '/' : (string) $pointer] = array_values(array_map('strval', (array) $messages));
        }

        return $out === [] ? ['/' => ['Invalid document.']] : $out;
    }

    public function isValid(string $schemaId, mixed $document): bool
    {
        return $this->errors($schemaId, $document) === [];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $root
     */
    private function coerce(mixed $data, array $schema, array $root, int $depth = 0): mixed
    {
        if ($depth > 64) {
            return $data;
        }
        if (isset($schema['$ref'])) {
            [$schema, $root] = $this->resolve($schema['$ref'], $root);
        }
        if (is_array($data) && $data === [] && $this->wantsObject($schema, $root)) {
            return new stdClass;
        }
        foreach (['oneOf', 'anyOf', 'allOf'] as $k) {
            if (isset($schema[$k]) && (is_array($data) || $data instanceof stdClass)) {
                foreach ($schema[$k] as $branch) {
                    $data = $this->coerce($data, $branch, $root, $depth + 1);
                }
            }
        }
        if ($data instanceof stdClass) {
            foreach (get_object_vars($data) as $key => $value) {
                $sub = $schema['properties'][$key] ?? (is_array($schema['additionalProperties'] ?? null) ? $schema['additionalProperties'] : null);
                if ($sub !== null) {
                    $data->{$key} = $this->coerce($value, $sub, $root, $depth + 1);
                }
            }
        } elseif (is_array($data) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($data as $i => $value) {
                $data[$i] = $this->coerce($value, $schema['items'], $root, $depth + 1);
            }
        }

        return $data;
    }

    /** @param  array<string, mixed>  $root */
    private function wantsObject(array $schema, array $root, int $depth = 0): bool
    {
        if ($depth > 16) {
            return false;
        }
        if (isset($schema['$ref'])) {
            [$schema, $root] = $this->resolve($schema['$ref'], $root);
        }
        $type = $schema['type'] ?? null;
        if ($type === 'object' || (is_array($type) && in_array('object', $type, true) && ! in_array('array', $type, true))) {
            return true;
        }
        foreach (['oneOf', 'anyOf'] as $k) {
            foreach ($schema[$k] ?? [] as $branch) {
                if ($this->wantsObject($branch, $root, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function resolve(string $ref, array $root): array
    {
        [$doc, $fragment] = array_pad(explode('#', $ref, 2), 2, '');
        $base = $doc === '' ? $root : ($this->raw[$doc] ?? []);
        $node = $base;
        foreach (array_filter(explode('/', $fragment), static fn ($p) => $p !== '') as $part) {
            $node = $node[str_replace(['~1', '~0'], ['/', '~'], $part)] ?? [];
        }

        return [$node, $base];
    }

    private function validator(): Validator
    {
        if ($this->validator !== null) {
            return $this->validator;
        }
        $validator = new Validator;
        $validator->setMaxErrors(20);
        $resolver = $validator->resolver();
        $files = [app_path('Expressions/expression-ast.schema.json'), ...(glob(app_path('Modules/*/Schemas/*.schema.json')) ?: [])];
        foreach ($files as $file) {
            $json = (string) file_get_contents($file);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->raw[$decoded['$id']] = $decoded;
            $resolver?->registerRaw($json, $decoded['$id']);
        }

        return $this->validator = $validator;
    }
}
