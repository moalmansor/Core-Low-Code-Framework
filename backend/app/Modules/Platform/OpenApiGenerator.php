<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Forms\FieldTypes\FieldTypeRegistry;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\ApiToken;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Facades\DB;

/**
 * The OpenAPI 3.1 document of the data API (specification §4.22), generated
 * for the caller: only the forms their token may read and they may view,
 * and in each only the fields they may see. Writable forms get the write
 * operations; transitions are listed by key.
 */
final class OpenApiGenerator
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly FieldAccessResolver $fieldAccess,
    ) {}

    /** @return array<string, mixed> */
    public function document(User $user, ?ApiToken $token): array
    {
        $paths = [];
        $schemas = ['Error' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string'], 'code' => ['type' => 'string'], 'errors' => ['type' => 'object']]]];
        foreach (Form::query()->whereIn('state', ['published', 'schema_inconsistent'])->orderBy('key')->get() as $form) {
            if ($token === null || ! $token->allowsForm((string) $form->uuid, false) || ! $this->access->allows($user, "form.{$form->uuid}.view")) {
                continue;
            }
            $rt = $this->runtimes->forForm($form);
            if ($rt === null) {
                continue;
            }
            $key = (string) $form->key;
            $name = 'Record_'.$key;
            $schemas[$name] = $this->recordSchema($rt, $user);
            $schemas[$name.'_values'] = $schemas[$name]['properties']['values'];
            $write = $token->allowsForm((string) $form->uuid, true);
            $label = (string) ($form->translate('name') ?? $key);
            $ref = ['$ref' => '#/components/schemas/'.$name];
            $one = ['description' => 'The record', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $ref]]]]];
            $errors = ['401' => ['description' => 'No valid token'], '403' => ['description' => 'Not permitted'], '404' => ['description' => 'Not found'], '409' => ['description' => 'Changed by someone else (row_version)'], '422' => ['description' => 'Invalid values', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]], '429' => ['description' => 'Rate limit reached']];
            $list = ['get' => ['tags' => [$label], 'summary' => "List {$label}", 'operationId' => "list_{$key}",
                'parameters' => [
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                    ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'A field key'],
                    ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                ],
                'responses' => ['200' => ['description' => 'A page of records', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                    'data' => ['type' => 'array', 'items' => $ref], 'meta' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer']]],
                ]]]]]] + $errors]];
            $item = ['get' => ['tags' => [$label], 'summary' => "Read one {$label}", 'operationId' => "get_{$key}", 'responses' => ['200' => $one] + $errors]];
            $uuid = ['name' => 'record', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']];
            if ($write) {
                $values = ['$ref' => '#/components/schemas/'.$name.'_values'];
                $list['post'] = ['tags' => [$label], 'summary' => "Create {$label}", 'operationId' => "create_{$key}",
                    'parameters' => [['name' => 'Idempotency-Key', 'in' => 'header', 'schema' => ['type' => 'string', 'minLength' => 16, 'maxLength' => 64]]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['values'], 'properties' => ['values' => $values]]]]],
                    'responses' => ['201' => $one] + $errors];
                $item['patch'] = ['tags' => [$label], 'summary' => "Update {$label}", 'operationId' => "update_{$key}",
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['values', 'row_version'], 'properties' => ['values' => $values, 'row_version' => ['type' => 'integer', 'description' => 'The row_version you read; a newer one answers 409']]]]]],
                    'responses' => ['200' => $one] + $errors];
                $item['delete'] = ['tags' => [$label], 'summary' => "Delete {$label}", 'operationId' => "delete_{$key}",
                    'parameters' => [['name' => 'row_version', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses' => ['204' => ['description' => 'Deleted (kept in the recycle bin)']] + $errors];
                $transitions = DB::table('transitions')->where('form_id', $form->id)->orderBy('key')->pluck('key')->all();
                if ($transitions !== []) {
                    $paths["/data/{$key}/{record}/transitions/{transition}"] = ['post' => ['tags' => [$label], 'summary' => "Move a {$label} through its workflow", 'operationId' => "transition_{$key}",
                        'parameters' => [$uuid, ['name' => 'transition', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => array_values(array_map('strval', $transitions))]]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['row_version'], 'properties' => ['row_version' => ['type' => 'integer'], 'comment' => ['type' => 'string'], 'values' => $values]]]]],
                        'responses' => ['200' => ['description' => 'Moved']] + $errors]];
                }
            }
            $item['parameters'] = [$uuid];
            $paths["/data/{$key}"] = $list;
            $paths["/data/{$key}/{record}"] = $item;
        }
        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Data API', 'version' => '1', 'description' => 'Records of the forms this token may reach. Each call acts as the token owner, with their permissions, record access and field access.'],
            'servers' => [['url' => url('/api/v1')]],
            'security' => [['bearer' => []]],
            'paths' => (object) $paths,
            'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'A personal API token (Profile › API tokens)']], 'schemas' => $schemas],
        ];
    }

    /** @return array<string, mixed> */
    private function recordSchema(FormRuntime $rt, User $user): array
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, (string) $rt->form->uuid, $rt->definition, 'view')['fields'];
        $props = [];
        foreach ($rt->mainFields() as $f) {
            if (($levels[$f['uuid']] ?? 'hidden') === 'hidden' || ! FieldTypeRegistry::has((string) $f['type'])) {
                continue;
            }
            $props[(string) $f['key']] = $this->valueSchema((string) $f['type'], $f);
        }
        foreach ($rt->repeaters as $repUuid => $rep) {
            $row = [];
            foreach ($rt->rowFields($repUuid) as $f) {
                if (($levels[$f['uuid']] ?? 'hidden') !== 'hidden' && FieldTypeRegistry::has((string) $f['type'])) {
                    $row[(string) $f['key']] = $this->valueSchema((string) $f['type'], $f);
                }
            }
            if ($row !== []) {
                $props[(string) $rep['group']['key']] = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => $row]];
            }
        }

        return ['type' => 'object', 'properties' => [
            'uuid' => ['type' => 'string', 'format' => 'uuid'],
            'row_version' => ['type' => 'integer'],
            'title' => ['type' => ['string', 'null']],
            'values' => ['type' => 'object', 'properties' => $props === [] ? (object) [] : $props],
        ]];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function valueSchema(string $type, array $field): array
    {
        $t = FieldTypeRegistry::get($type);
        $one = match ($t->valueType) {
            'number' => ['type' => 'number'],
            'boolean' => ['type' => 'boolean'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'time' => ['type' => 'string', 'pattern' => '^\\d{2}:\\d{2}(:\\d{2})?$'],
            'duration' => ['type' => 'integer', 'description' => 'minutes'],
            'record' => ['type' => 'string', 'format' => 'uuid', 'description' => 'the linked record'],
            'list' => ['type' => 'array', 'items' => ['type' => 'string']],
            'any' => (object) [],
            default => ['type' => 'string'],
        };
        if (is_array($one) && ($field['i18n']['label'] ?? null) !== null) {
            $one['title'] = (string) (array_values(array_filter((array) $field['i18n']['label']))[0] ?? $field['key']);
        }
        if ($t->multiple && is_array($one) && $one['type'] !== 'array') {
            return ['type' => 'array', 'items' => $one];
        }

        return $one;
    }
}
