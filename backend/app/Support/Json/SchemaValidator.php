<?php

declare(strict_types=1);

namespace App\Support\Json;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates metadata JSON against the JSON Schema (draft 2020-12) documents of
 * architecture §14. Schemas are registered by `$id` from every
 * `app/Modules/*\/Schemas/*.schema.json` file and the expression AST schema,
 * so documents can `$ref` each other.
 */
final class SchemaValidator
{
    private ?Validator $validator = null;

    /**
     * @return array<string, list<string>> JSON pointer => messages; empty when valid
     */
    public function errors(string $schemaId, mixed $document): array
    {
        $data = json_decode((string) json_encode($document, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), false, 512, JSON_THROW_ON_ERROR);
        $result = $this->validator()->validate($data, $schemaId);
        if ($result->isValid()) {
            return [];
        }

        return (new ErrorFormatter)->formatFlat($result->error()) === []
            ? ['/' => ['Invalid document.']]
            : $this->flatten((new ErrorFormatter)->format($result->error(), true));
    }

    public function isValid(string $schemaId, mixed $document): bool
    {
        return $this->errors($schemaId, $document) === [];
    }

    /**
     * @param  array<string, mixed>  $formatted
     * @return array<string, list<string>>
     */
    private function flatten(array $formatted): array
    {
        $out = [];
        foreach ($formatted as $pointer => $messages) {
            $out[$pointer === '' ? '/' : (string) $pointer] = array_values(array_map('strval', (array) $messages));
        }

        return $out;
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
            $id = json_decode($json, false, 512, JSON_THROW_ON_ERROR)->{'$id'};
            $resolver?->registerRaw($json, $id);
        }

        return $this->validator = $validator;
    }
}
