<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;

/**
 * The fields reachable from a form, as the relation-path pickers offer them
 * (views, panels, recipients, template placeholders): its own stored fields,
 * the fields of the forms it links to followed through references, and the
 * system columns at every level, up to the path depth limit. Encrypted fields
 * are never offered. Labels stay per language for the browser to pick.
 */
final class FieldTree
{
    public function __construct(private readonly FormRuntimes $runtimes) {}

    /**
     * @param  list<string>  $prefix
     * @return list<array<string, mixed>>
     */
    public function build(FormRuntime $rt, array $prefix = [], int $depth = 0): array
    {
        $out = [];
        foreach ($rt->mainFields() as $f) {
            if (! $rt->isStored($f) || ($f['flags']['encrypted'] ?? false)) {
                continue;
            }
            $type = $rt->type($f);
            $node = [
                'key' => $f['key'], 'path' => [...$prefix, $f['key']], 'type' => $f['type'], 'storage' => $type?->storage,
                'kind' => $type?->key === 'email' ? 'email' : (in_array($type?->storage, ['user', 'role', 'department'], true) ? $type->storage : null),
                'label' => $f['i18n']['label'] ?? [], 'children' => [],
            ];
            $target = $rt->targetFormUuid($f);
            if ($target !== null && $depth < RelationPaths::MAX_DEPTH - 2) {
                $trt = $this->runtimes->forUuid($target);
                if ($trt !== null && $trt->form->id !== $rt->form->id) {
                    $node['children'] = $this->build($trt, [...$prefix, $f['key']], $depth + 1);
                    $node['form'] = ['uuid' => $trt->form->uuid, 'key' => $trt->form->key];
                }
            }
            $out[] = $node;
        }
        foreach (['@status', '@record_number', '@created_at', '@updated_at'] as $system) {
            $out[] = [
                'key' => $system, 'path' => [...$prefix, $system], 'type' => 'system', 'storage' => in_array($system, ['@created_at', '@updated_at'], true) ? 'datetime' : 'string', 'kind' => null,
                'label' => ['en' => __('views.system_'.substr($system, 1), [], 'en'), 'ar' => __('views.system_'.substr($system, 1), [], 'ar')], 'children' => [],
            ];
        }

        return $out;
    }
}
