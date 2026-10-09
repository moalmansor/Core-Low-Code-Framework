<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

use App\Expressions\Evaluation\Ast;

/**
 * The definition sent to the browser (architecture §3.1): the published
 * definition minus server-only data (physical schema, column names,
 * encryption flags, hook bindings, access rules, target tables) and minus
 * every field the user may not see. Rules and calculations that read a hidden
 * field are evaluated on the server only, so they are dropped here and the
 * affected fields are marked `serverComputed`.
 */
final class ClientDefinition
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}  $access
     * @return array<string, mixed>
     */
    public function build(array $definition, array $access, string $mode): array
    {
        $hiddenFields = array_keys(array_filter($access['fields'], static fn (string $l) => $l === 'hidden'));
        $hiddenGroups = array_keys(array_filter($access['groups'], static fn (string $l) => $l === 'hidden'));
        $hiddenKeys = [];
        foreach ($definition['fields'] as $f) {
            if (in_array($f['uuid'], $hiddenFields, true)) {
                $hiddenKeys[$f['key']] = true;
            }
        }
        $readsHidden = static function (?array $ast) use ($hiddenKeys): bool {
            if ($ast === null) {
                return false;
            }
            foreach (Ast::references($ast) as $ref) {
                if (in_array($ref['scope'], ['record', 'old', 'row', 'parent'], true) && isset($hiddenKeys[$ref['path'][0]])) {
                    return true;
                }
            }

            return false;
        };

        $fields = [];
        foreach ($definition['fields'] as $f) {
            if (in_array($f['uuid'], $hiddenFields, true)) {
                continue;
            }
            $behavior = $f['behavior'] ?? [];
            $serverComputed = false;
            if ($readsHidden($behavior['formula'] ?? null)) {
                $behavior['formula'] = null;
                $serverComputed = true;
            }
            if ($readsHidden($behavior['default']['expr'] ?? null)) {
                $behavior['default'] = null;
                $serverComputed = true;
            }
            $storage = $f['storage'] ?? [];
            $fields[] = [
                'uuid' => $f['uuid'],
                'key' => $f['key'],
                'type' => $f['type'],
                'group' => $f['group'],
                'order' => $f['order'],
                'storage' => array_intersect_key($storage, array_flip(['length', 'precision', 'scale', 'multiCurrency'])),
                'options' => $f['options'] ?? null,
                'validation' => $f['validation'] ?? [],
                'behavior' => $behavior,
                'ui' => $f['ui'] ?? [],
                'table' => $f['table'] ?? [],
                'events' => array_values(array_filter($f['events'] ?? [], static fn (array $e) => ! $readsHidden($e['when'] ?? null))),
                'flags' => ['sensitive' => (bool) ($f['flags']['sensitive'] ?? false)],
                'relation' => $f['relation'] ?? null,
                'i18n' => $f['i18n'] ?? [],
                'access' => $access['fields'][$f['uuid']] ?? 'read_only',
                'serverComputed' => $serverComputed,
            ];
        }
        $groups = [];
        foreach ($definition['groups'] as $g) {
            if (in_array($g['uuid'], $hiddenGroups, true)) {
                continue;
            }
            $groups[] = $g + ['access' => $access['groups'][$g['uuid']] ?? 'read_only'];
        }
        $conditions = [];
        foreach ($definition['conditions'] as $c) {
            if (! ($c['active'] ?? true) || ($c['runtime'] ?? 'client_and_server') === 'server_only' || $readsHidden($c['when'])) {
                continue;
            }
            $values = array_filter([...$c['effects'], ...($c['else'] ?? [])], static fn (array $e) => isset($e['value']));
            if (array_filter($values, static fn (array $e) => $readsHidden($e['value'])) !== []) {
                continue;
            }
            $conditions[] = $c;
        }
        $relations = array_map(static fn (array $r) => array_intersect_key($r, array_flip(['uuid', 'key', 'type', 'target', 'kind', 'display', 'value'])), $definition['relations']);
        $form = $definition['form'];

        return [
            'form' => [
                'uuid' => $form['uuid'],
                'key' => $form['key'],
                'kind' => $form['kind'],
                'version' => $form['version'] ?? null,
                'icon' => $form['icon'] ?? null,
                'settings' => $form['settings'] ?? [],
                'titleTemplate' => $form['titleTemplate'] ?? null,
                'i18n' => $form['i18n'] ?? [],
            ],
            'mode' => $mode,
            'access' => ['form' => $access['form'], 'modes' => $access['modes']],
            'groups' => $groups,
            'fields' => $fields,
            'relations' => $relations,
            'conditions' => $conditions,
        ];
    }
}
