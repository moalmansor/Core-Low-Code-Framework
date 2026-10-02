<?php

declare(strict_types=1);

namespace App\Modules\Setup;

use App\Modules\Core\Settings\SettingsService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Whether first-run setup is finished (specification §2), and the one-time
 * setup token that proves the person running the wizard has console access to
 * the installation. Only the token's SHA-256 hash is stored.
 */
final class SetupState
{
    public function __construct(private readonly SettingsService $settings) {}

    public function isComplete(): bool
    {
        try {
            return $this->settings->get('setup', 'completed_at') !== null;
        } catch (Throwable) {
            return false; // not migrated yet
        }
    }

    public function hasToken(): bool
    {
        return is_string($this->settings->get('setup', 'token_hash'));
    }

    /** Issue a fresh token (invalidating any earlier one) and return it once. */
    public function issueToken(): string
    {
        $token = Str::lower(implode('-', str_split(bin2hex(random_bytes(12)), 6)));
        $this->settings->write('setup', 'token_hash', hash('sha256', $token));

        return $token;
    }

    public function checkToken(?string $candidate): bool
    {
        $hash = $this->settings->get('setup', 'token_hash');
        if (! is_string($hash) || ! is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($hash, hash('sha256', Str::lower(trim($candidate))));
    }

    public function discardToken(): void
    {
        $this->settings->write('setup', 'token_hash', null);
    }
}
