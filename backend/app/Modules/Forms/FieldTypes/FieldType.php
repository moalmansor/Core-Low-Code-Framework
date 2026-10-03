<?php

declare(strict_types=1);

namespace App\Modules\Forms\FieldTypes;

/**
 * One palette entry of the field type registry (architecture §14.4): how the
 * type is stored (§11.3), which validation rules and behaviors apply, whether
 * it takes options, how lists filter it, and which client component renders it.
 */
final readonly class FieldType
{
    /**
     * @param  string  $storage  storage strategy: none, string, text, longtext, number, int, decimal, bool, date, time,
     *                           datetime, duration, json, choice, multi_choice, lookup, multi_lookup, user, role,
     *                           department, file, files, range_date, range_time, range_datetime, map, phone, currency,
     *                           consent, auto_number, formula
     * @param  list<string>  $validation  allowed validation rule groups (§14.6)
     * @param  string  $valueType  expression type of the value (expression-language.md §3)
     */
    public function __construct(
        public string $key,
        public string $category,
        public string $storage,
        public string $valueType,
        public array $validation = [],
        public bool $options = false,
        public bool $multiple = false,
        public string $filter = 'none',
        public bool $native = false,
        public string $icon = 'pi pi-pencil',
        public int $defaultLength = 255,
        public ?int $defaultPrecision = null,
        public ?int $defaultScale = null,
        public bool $calculated = false,
    ) {}

    public function isStored(): bool
    {
        return $this->storage !== 'none';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'category' => $this->category,
            'stored' => $this->isStored(),
            'storage' => $this->storage,
            'value_type' => $this->valueType,
            'validation' => $this->validation,
            'options' => $this->options,
            'multiple' => $this->multiple,
            'filter' => $this->filter,
            'native' => $this->native,
            'icon' => $this->icon,
            'calculated' => $this->calculated,
            'defaults' => array_filter([
                'length' => in_array($this->storage, ['string', 'choice', 'auto_number'], true) ? $this->defaultLength : null,
                'precision' => $this->defaultPrecision,
                'scale' => $this->defaultScale,
            ], static fn ($v) => $v !== null),
        ];
    }
}
