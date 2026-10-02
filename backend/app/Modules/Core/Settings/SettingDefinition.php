<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings;

/**
 * One configurable setting: default value, validation rules, and whether it
 * is a secret (stored encrypted, write-only through the API).
 */
final readonly class SettingDefinition
{
    /**
     * @param  list<mixed>|string  $rules  Laravel validation rules for the value
     */
    public function __construct(
        public string $group,
        public string $key,
        public mixed $default,
        public array|string $rules,
        public bool $secret = false,
        public bool $immutableAfterSetup = false,
    ) {}
}
