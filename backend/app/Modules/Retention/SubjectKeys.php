<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Crypto-shredding of personal values in immutable logs (ADR-0014,
 * architecture §19.16). Values of fields marked as personal data are written
 * into audit `changes` and merge snapshots sealed with a key of their own
 * record (`subject_keys`). Anonymizing or deleting the person's data destroys
 * the key: the ciphertext, and so every hash of the audit chain, stays as it
 * was, while the values can no longer be read by anyone.
 *
 * A sealed value is the string `sealed:v1:` + base64(nonce ‖ secretbox).
 * The record key is random and kept wrapped with the application key.
 */
final class SubjectKeys
{
    public const PREFIX = 'sealed:v1:';

    /** Shown in place of a value whose key has been destroyed. */
    public const ERASED = '«erased»';

    /** Shown in place of a sealed value outside the record's own history. */
    public const PERSONAL = '«personal»';

    /** @var array<string, string|null> raw keys of this request, by "form:record" or "user:id"; null once destroyed */
    private array $keys = [];

    /** Seals a value with its record's key, making the key when the record has none yet. */
    public function seal(int $formId, int $recordId, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $key = $this->key($formId, $recordId, true);
        if ($key === null) {
            // The person's data was erased; a value written afterwards is not recorded in clear either.
            return self::ERASED;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox((string) json_encode($value, JSON_UNESCAPED_UNICODE), $nonce, $key));
    }

    public static function isSealed(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** Opens a value sealed for a record; «erased» when its key was destroyed, the value itself when not sealed. */
    public function open(?int $formId, ?int $recordId, mixed $value): mixed
    {
        if (! self::isSealed($value)) {
            return $value;
        }
        $key = $formId === null || $recordId === null ? null : $this->key($formId, $recordId, false);
        $raw = base64_decode(substr((string) $value, strlen(self::PREFIX)), true);
        if ($key === null || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return self::ERASED;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);

        return $plain === false ? self::ERASED : json_decode($plain, true);
    }

    /**
     * Opens every sealed old/new value of an audit entry's changes.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    public function openChanges(?int $formId, ?int $recordId, array $changes): array
    {
        return array_map(function (array $c) use ($formId, $recordId): array {
            foreach (['old', 'new'] as $side) {
                if (array_key_exists($side, $c)) {
                    $c[$side] = $this->open($formId, $recordId, $c[$side]);
                }
            }

            return $c;
        }, $changes);
    }

    /**
     * Replaces sealed values with a placeholder, for views that are not tied
     * to one record's field permissions (the audit log viewer and its export).
     *
     * @param  list<array<string, mixed>>|null  $changes
     * @return list<array<string, mixed>>|null
     */
    public static function redact(?array $changes): ?array
    {
        return $changes === null ? null : array_map(static function ($c) {
            foreach (['old', 'new'] as $side) {
                if (is_array($c) && self::isSealed($c[$side] ?? null)) {
                    $c[$side] = self::PERSONAL;
                }
            }

            return $c;
        }, $changes);
    }

    /**
     * Destroys the keys of these records: their sealed values become
     * unreadable for good. A record that never had a key gets a destroyed
     * one, so nothing sealed for it later can be read either.
     *
     * @param  list<int>  $recordIds
     */
    public function destroy(int $organizationId, int $formId, array $recordIds, ?int $requestId): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $n = 0;
        foreach (array_chunk(array_values(array_unique($recordIds)), 500) as $chunk) {
            $have = DB::table('subject_keys')->where('form_id', $formId)->whereIn('record_id', $chunk)->pluck('record_id')->map(static fn ($v) => (int) $v)->all();
            $n += DB::table('subject_keys')->where('form_id', $formId)->whereIn('record_id', $chunk)->whereNull('destroyed_at')
                ->update(['wrapped_key' => null, 'destroyed_at' => $now, 'destroyed_by_request_id' => $requestId, 'updated_at' => $now]);
            $missing = array_diff($chunk, $have);
            if ($missing !== []) {
                DB::table('subject_keys')->insert(array_map(static fn (int $id) => [
                    'organization_id' => $organizationId, 'created_at' => $now, 'updated_at' => $now, 'form_id' => $formId, 'record_id' => $id,
                    'wrapped_key' => null, 'destroyed_at' => $now, 'destroyed_by_request_id' => $requestId,
                ], array_values($missing)));
                $n += count($missing);
            }
            foreach ($chunk as $id) {
                $this->keys["{$formId}:{$id}"] = null;
            }
        }

        return $n;
    }

    private function key(int $formId, int $recordId, bool $create): ?string
    {
        $slot = "{$formId}:{$recordId}";
        if (array_key_exists($slot, $this->keys)) {
            return $this->keys[$slot];
        }
        $row = DB::table('subject_keys')->where('form_id', $formId)->where('record_id', $recordId)->first(['wrapped_key', 'destroyed_at']);
        if ($row === null && $create) {
            $raw = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('subject_keys')->insert([
                'organization_id' => (int) DB::table('forms')->where('id', $formId)->value('organization_id'), 'created_at' => $now, 'updated_at' => $now,
                'form_id' => $formId, 'record_id' => $recordId, 'wrapped_key' => Crypt::encryptString(base64_encode($raw)),
            ]);

            return $this->keys[$slot] = $raw;
        }
        if ($row === null) {
            return null; // nothing sealed for this record yet
        }
        if ($row->destroyed_at !== null || $row->wrapped_key === null) {
            return $this->keys[$slot] = null;
        }

        return $this->keys[$slot] = (string) base64_decode(Crypt::decryptString((string) $row->wrapped_key), true);
    }
}
