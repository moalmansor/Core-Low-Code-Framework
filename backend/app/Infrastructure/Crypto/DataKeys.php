<?php

declare(strict_types=1);

namespace App\Infrastructure\Crypto;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Per-organization data keys (specification §5, architecture §7.5): an
 * envelope scheme for encrypted field values and their blind indexes.
 *
 * Each organization has one active key per purpose (`fields`,
 * `blind_index`) in `encryption_keys`, wrapped with the application master
 * key (APP_KEY, which can itself be rotated with APP_PREVIOUS_KEYS and
 * `keys:rewrap`). A value is stored as `v1:{keyId}:{base64(iv ‖ tag ‖
 * ciphertext)}` (AES-256-GCM), so every ciphertext names the key that opens
 * it and `keys:rotate` can move data to a new key while older keys keep
 * opening what has not been moved yet. Values written before data keys
 * existed (plain Laravel ciphertexts) still open, and are moved by the first
 * rotation.
 *
 * Blind indexes use the organization's `blind_index` key once it has one;
 * until then the application-key HMAC they were created with.
 */
final class DataKeys
{
    public const PREFIX = 'v1:';

    /** @var array<string, string> raw keys by key id */
    private array $raw = [];

    /** @var array<string, string|null> active key id by "org:purpose" */
    private array $active = [];

    public function encrypt(int $organizationId, string $purpose, string $plain): string
    {
        $keyId = $this->activeId($organizationId, $purpose, true);
        $key = $this->key((string) $keyId);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return self::PREFIX.$keyId.':'.base64_encode($iv.$tag.$cipher);
    }

    public function decrypt(string $value): string
    {
        if (! str_starts_with($value, self::PREFIX)) {
            return Crypt::decryptString($value); // written before data keys
        }
        [, $keyId, $payload] = explode(':', $value, 3) + [null, null, null];
        $raw = base64_decode((string) $payload, true);
        if ($keyId === null || $raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Malformed ciphertext.');
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key($keyId), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $plain;
    }

    /** The key id a ciphertext was made with (null for one written before data keys). */
    public static function keyIdOf(string $value): ?string
    {
        return str_starts_with($value, self::PREFIX) ? explode(':', $value, 3)[1] ?? null : null;
    }

    /** Keyed blind index of an already normalized text, for exact-match search on encrypted fields. */
    public function blindIndex(?int $organizationId, string $normalized): string
    {
        $keyId = $organizationId === null ? null : $this->activeId($organizationId, 'blind_index', false);

        return $keyId === null
            ? hash_hmac('sha256', $normalized, 'bidx|'.config('app.key'))
            : hash_hmac('sha256', $normalized, $this->key($keyId));
    }

    /**
     * Makes a new active key for an organization and purpose; the previous
     * one becomes `retiring` (it still opens what it encrypted). Returns the
     * new key id.
     */
    public function rotate(int $organizationId, string $purpose): string
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (string) Str::uuid7();
        DB::transaction(function () use ($organizationId, $purpose, $now, $id): void {
            DB::table('encryption_keys')->where('organization_id', $organizationId)->where('purpose', $purpose)->where('status', 'active')
                ->update(['status' => 'retiring', 'rotated_at' => $now, 'updated_at' => $now]);
            DB::table('encryption_keys')->insert([
                'organization_id' => $organizationId, 'created_at' => $now, 'updated_at' => $now, 'key_id' => $id,
                'wrapped_key' => Crypt::encryptString(base64_encode(random_bytes(32))), 'algorithm' => 'aes-256-gcm', 'purpose' => $purpose, 'status' => 'active',
            ]);
        });
        $this->active = [];

        return $id;
    }

    /** Marks an organization's retiring keys of a purpose as retired once nothing uses them. */
    public function retire(int $organizationId, string $purpose): int
    {
        return DB::table('encryption_keys')->where('organization_id', $organizationId)->where('purpose', $purpose)->where('status', 'retiring')
            ->update(['status' => 'retired', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    public function forget(): void
    {
        $this->raw = [];
        $this->active = [];
    }

    private function activeId(int $organizationId, string $purpose, bool $create): ?string
    {
        $slot = "{$organizationId}:{$purpose}";
        if (! array_key_exists($slot, $this->active) || ($this->active[$slot] === null && $create)) {
            $id = DB::table('encryption_keys')->where('organization_id', $organizationId)->where('purpose', $purpose)->where('status', 'active')->value('key_id');
            $this->active[$slot] = $id === null ? ($create ? $this->rotate($organizationId, $purpose) : null) : (string) $id;
        }

        return $this->active[$slot];
    }

    private function key(string $keyId): string
    {
        if (! isset($this->raw[$keyId])) {
            $wrapped = DB::table('encryption_keys')->where('key_id', $keyId)->value('wrapped_key');
            if ($wrapped === null) {
                throw new RuntimeException('Unknown data key.');
            }
            $this->raw[$keyId] = (string) base64_decode(Crypt::decryptString((string) $wrapped), true);
        }

        return $this->raw[$keyId];
    }
}
