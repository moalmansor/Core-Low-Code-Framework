<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Reads and writes settings (architecture §10.2). Secrets are encrypted at rest
 * and never returned; every change is audited with secrets masked.
 */
final class SettingsService
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly TenantContext $tenant,
    ) {}

    public function get(string $group, string $key): mixed
    {
        $definition = $this->definition($group, $key);
        $values = $this->loadGroup($group);

        return array_key_exists($key, $values) ? $values[$key] : $definition->default;
    }

    /**
     * Group values for the API: secrets are replaced by `{is_set: bool}`.
     *
     * @return array<string, mixed>
     */
    public function publicGroup(string $group): array
    {
        $values = $this->loadGroup($group);
        $out = [];
        foreach ($this->registry->group($group) as $key => $definition) {
            $out[$key] = $definition->secret
                ? ['is_set' => array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== []]
                : (array_key_exists($key, $values) ? $values[$key] : $definition->default);
        }

        return $out;
    }

    /**
     * Validate and persist a partial update of one group.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(string $group, array $input): void
    {
        $definitions = $this->registry->group($group);
        if ($definitions === []) {
            throw new InvalidArgumentException("Unknown settings group [{$group}].");
        }
        $unknown = array_diff(array_keys($input), array_keys($definitions));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, __('validation.prohibited', ['attribute' => 'setting'])));
        }
        $rules = [];
        foreach ($input as $key => $value) {
            $definition = $definitions[$key];
            if ($definition->immutableAfterSetup && $this->get('setup', 'completed_at') !== null) {
                throw ValidationException::withMessages([$key => __('ui.settings.immutable_after_setup')]);
            }
            // An omitted or empty secret means "keep the current value".
            if ($definition->secret && ($value === null || $value === '')) {
                unset($input[$key]);

                continue;
            }
            // String-keyed entries are rules for nested values ('*.key' => [...]).
            $own = $definition->rules;
            if (is_array($own)) {
                foreach ($own as $sub => $rule) {
                    if (is_string($sub)) {
                        $rules["{$key}.{$sub}"] = $rule;
                        unset($own[$sub]);
                    }
                }
                $own = array_values($own);
            }
            $rules[$key] = $own;
        }
        Validator::make($input, $rules)->validate();

        DB::transaction(function () use ($group, $input, $definitions): void {
            $changes = [];
            foreach ($input as $key => $value) {
                $definition = $definitions[$key];
                $old = $this->get($group, $key);
                $this->write($group, $key, $value, $definition->secret);
                $changes[] = [
                    'field_key' => "{$group}.{$key}",
                    'old' => $definition->secret ? '«secret»' : $old,
                    'new' => $definition->secret ? '«secret»' : $value,
                ];
            }
            if ($changes !== []) {
                app(AuditWriter::class)->record('config.changed', 'config', changes: $changes, objectType: 'settings', meta: ['group' => $group]);
            }
        });
        $this->forgetGroup($group);
    }

    /** Write without validation or audit (seeders and setup). */
    public function write(string $group, string $key, mixed $value, ?bool $secret = null): void
    {
        $secret ??= $this->definition($group, $key)->secret;
        Setting::query()->updateOrCreate(
            ['organization_id' => $this->tenant->organizationId(), 'group' => $group, 'key' => $key],
            [
                'value' => $secret ? null : $value,
                'encrypted_value' => $secret && $value !== null ? Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)) : null,
                'is_encrypted' => $secret,
                'updated_by' => Auth::id(),
            ],
        );
        $this->forgetGroup($group);
    }

    public function forgetGroup(string $group): void
    {
        Cache::forget($this->cacheKey($group));
    }

    /**
     * Group values with secrets decrypted in memory only. The cache holds the
     * stored (still encrypted) form so plaintext secrets never reach Redis.
     *
     * @return array<string, mixed>
     */
    private function loadGroup(string $group): array
    {
        /** @var array<string, array{0: bool, 1: mixed}> $stored */
        $stored = Cache::remember($this->cacheKey($group), 300, static function () use ($group): array {
            $rows = [];
            foreach (Setting::query()->where('group', $group)->get() as $setting) {
                $rows[$setting->key] = $setting->is_encrypted ? [true, $setting->encrypted_value] : [false, $setting->value];
            }

            return $rows;
        });
        $values = [];
        foreach ($stored as $key => [$encrypted, $value]) {
            $values[$key] = $encrypted && is_string($value)
                ? json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR)
                : ($encrypted ? null : $value);
        }

        return $values;
    }

    private function definition(string $group, string $key): SettingDefinition
    {
        return $this->registry->get($group, $key)
            ?? throw new InvalidArgumentException("Unknown setting [{$group}.{$key}].");
    }

    private function cacheKey(string $group): string
    {
        return 'settings:'.$this->tenant->organizationId().':'.$group;
    }
}
