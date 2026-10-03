<?php

declare(strict_types=1);

namespace App\Modules\Core\I18n;

use Illuminate\Database\Eloquent\Model;

/**
 * Object types whose labels are translatable, so the translation manager can
 * list every (object × field × enabled locale) combination that has no value
 * (specification §3). Modules register their models at boot.
 */
final class TranslatableRegistry
{
    /** @var array<string, array{model: class-string<Model>, fields: list<string>, label: string}> */
    private array $types = [];

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $fields
     */
    public function register(string $type, string $model, array $fields, string $label): void
    {
        $this->types[$type] = ['model' => $model, 'fields' => $fields, 'label' => $label];
    }

    /** @return array<string, array{model: class-string<Model>, fields: list<string>, label: string}> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array{model: class-string<Model>, fields: list<string>, label: string}|null */
    public function get(string $type): ?array
    {
        return $this->types[$type] ?? null;
    }
}
