<?php

declare(strict_types=1);

namespace App\Infrastructure\Crypto;

use App\Modules\Core\Settings\SettingsRegistry;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\ValueCodec;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The key rotation procedure (specification §5, architecture §7.5; the steps
 * are in docs/guides/deployment.md).
 *
 * - `rotateFields()` gives an organization a new `fields` data key and
 *   re-encrypts every encrypted field value under it (values written before
 *   data keys existed are moved too), then retires the old key.
 * - `rotateBlindIndex()` gives it a new `blind_index` key and recomputes the
 *   blind index of every searchable encrypted field.
 * - `rewrap()` re-encrypts everything sealed with the application master key
 *   (wrapped data and subject keys, secret settings, two-factor secrets,
 *   integration and webhook secrets, journal payloads, merge snapshots and
 *   personal data request subjects) under the current APP_KEY, after the old
 *   key was moved to APP_PREVIOUS_KEYS, and drops the settings cache, which
 *   holds secret settings still sealed.
 *
 * Every update is compare-and-swap on the old ciphertext, so a value saved by
 * someone meanwhile is never overwritten; it is picked up by the next run.
 */
final class KeyRotation
{
    /** Columns holding one Laravel ciphertext each: table => columns. */
    private const SEALED_COLUMNS = [
        'encryption_keys' => ['wrapped_key'],
        'subject_keys' => ['wrapped_key'],
        'settings' => ['encrypted_value'],
        'users' => ['two_factor_secret', 'two_factor_recovery_codes'],
        'webhooks' => ['secret', 'headers'],
        'inbound_endpoints' => ['signature_secret'],
        'notification_channels' => ['config'],
        'external_data_sources' => ['credentials', 'headers'],
        'personal_data_requests' => ['subject'],
    ];

    /** JSON columns holding a Laravel ciphertext under one key: table => [column => json key]. */
    private const SEALED_JSON = [
        'submission_journal' => ['payload' => 'sealed', 'edited_payload' => 'sealed'],
        'merge_history' => ['merged_snapshots' => 'encrypted'],
    ];

    public function __construct(private readonly DataKeys $keys, private readonly FormRuntimes $runtimes) {}

    /** @return array{key: string, values: int, skipped: int} */
    public function rotateFields(int $organizationId): array
    {
        $keyId = $this->keys->rotate($organizationId, 'fields');
        $moved = 0;
        $skipped = 0;
        foreach ($this->encryptedColumns($organizationId) as [$table, $column]) {
            $this->eachRow($table, $column, static fn ($q) => $q->where($column, 'not like', DataKeys::PREFIX.$keyId.':%'), function (object $row) use ($organizationId, $table, $column, &$moved, &$skipped): void {
                try {
                    $next = $this->keys->encrypt($organizationId, 'fields', $this->keys->decrypt((string) $row->{$column}));
                } catch (Throwable) {
                    $skipped++;

                    return;
                }
                $moved += DB::table($table)->where('id', $row->id)->where($column, $row->{$column})->update([$column => $next]);
            });
        }
        $this->keys->retire($organizationId, 'fields');

        return ['key' => $keyId, 'values' => $moved, 'skipped' => $skipped];
    }

    /** @return array{key: string, values: int, skipped: int} */
    public function rotateBlindIndex(int $organizationId): array
    {
        $keyId = $this->keys->rotate($organizationId, 'blind_index');
        $done = 0;
        $skipped = 0;
        foreach ($this->encryptedColumns($organizationId) as [$table, $column, $bidx]) {
            if ($bidx === null) {
                continue;
            }
            $this->eachRow($table, $column, null, function (object $row) use ($organizationId, $table, $column, $bidx, &$done, &$skipped): void {
                try {
                    $value = json_decode($this->keys->decrypt((string) $row->{$column}), true);
                } catch (Throwable) {
                    $skipped++;

                    return;
                }
                $done += DB::table($table)->where('id', $row->id)->where($column, $row->{$column})->update([$bidx => ValueCodec::blindIndex($value, $organizationId)]);
            });
        }
        $this->keys->retire($organizationId, 'blind_index');

        return ['key' => $keyId, 'values' => $done, 'skipped' => $skipped];
    }

    /** @return array{values: int, skipped: int} */
    public function rewrap(): array
    {
        $done = 0;
        $skipped = 0;
        $reseal = static function (string $payload) use (&$skipped): ?string {
            try {
                return Crypt::encrypt(Crypt::decrypt($payload, false), false);
            } catch (Throwable) {
                $skipped++;

                return null;
            }
        };
        foreach (self::SEALED_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->eachRow($table, $column, null, function (object $row) use ($table, $column, $reseal, &$done): void {
                    $next = $reseal((string) $row->{$column});
                    if ($next !== null) {
                        $done += DB::table($table)->where('id', $row->id)->where($column, $row->{$column})->update([$column => $next]);
                    }
                });
            }
        }
        foreach (self::SEALED_JSON as $table => $columns) {
            foreach ($columns as $column => $key) {
                $this->eachRow($table, $column, null, function (object $row) use ($table, $column, $key, $reseal, &$done): void {
                    $doc = json_decode((string) $row->{$column}, true);
                    if (! is_array($doc) || ! is_string($doc[$key] ?? null)) {
                        return;
                    }
                    $next = $reseal($doc[$key]);
                    if ($next !== null) {
                        $doc[$key] = $next;
                        $done += DB::table($table)->where('id', $row->id)->where($column, $row->{$column})->update([$column => json_encode($doc, JSON_UNESCAPED_UNICODE)]);
                    }
                });
            }
        }
        // Webhook-call steps of actions keep their secret and header values sealed inside the step's configuration.
        $this->eachRow('action_steps', 'config', static fn ($q) => $q->where('type', 'call_webhook'), function (object $row) use ($reseal, &$done): void {
            $config = json_decode((string) $row->config, true) ?: [];
            if (is_string($config['secret'] ?? null)) {
                $config['secret'] = $reseal($config['secret']) ?? $config['secret'];
            }
            foreach ((array) ($config['headers'] ?? []) as $i => $h) {
                if (is_string($h['value'] ?? null)) {
                    $config['headers'][$i]['value'] = $reseal($h['value']) ?? $h['value'];
                }
            }
            $done += DB::table('action_steps')->where('id', $row->id)->where('config', $row->config)->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
        });
        $this->keys->forget();
        // The settings cache holds secret values still sealed (never in plaintext): drop it, so nothing sealed
        // with the old key is read once that key is gone.
        foreach (app(SettingsRegistry::class)->groups() as $group) {
            app(SettingsService::class)->forgetGroup($group);
        }

        return ['values' => $done, 'skipped' => $skipped];
    }

    /**
     * Encrypted columns of an organization's published forms, with their blind index column.
     *
     * @return list<array{0: string, 1: string, 2: string|null}>
     */
    private function encryptedColumns(int $organizationId): array
    {
        $out = [];
        foreach (Form::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereNotNull('current_version_id')->get() as $form) {
            $rt = $this->runtimes->forForm($form);
            foreach ($rt === null ? [] : $rt->definition['schema']['tables'] as $t) {
                foreach ($t['columns'] as $c) {
                    if (($c['encrypted'] ?? false) && ($c['part'] ?? null) === null) {
                        $bidx = collect($t['columns'])->first(static fn ($x) => ($x['field'] ?? null) === ($c['field'] ?? false) && ($x['part'] ?? null) === 'bidx');
                        $out[] = [(string) $t['name'], (string) $c['name'], $bidx === null ? null : (string) $bidx['name']];
                    }
                }
            }
        }

        return $out;
    }

    /** Walks the rows of a table that hold a value in the column, by id, in chunks. */
    private function eachRow(string $table, string $column, ?callable $where, callable $fn): void
    {
        $last = 0;
        while (true) {
            $q = DB::table($table)->whereNotNull($column)->where('id', '>', $last)->orderBy('id')->limit(500);
            if ($where !== null) {
                $where($q);
            }
            $rows = $q->get(['id', $column]);
            if ($rows->isEmpty()) {
                return;
            }
            foreach ($rows as $row) {
                $fn($row);
                $last = (int) $row->id;
            }
        }
    }
}
