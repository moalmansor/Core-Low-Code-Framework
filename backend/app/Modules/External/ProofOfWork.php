<?php

declare(strict_types=1);

namespace App\Modules\External;

use Illuminate\Support\Facades\Cache;

/**
 * The CAPTCHA of external forms (architecture §19.12, ADR-0013, ADR-0058): a
 * self-hosted proof of work, so no third party is called and nothing about
 * the visitor leaves the server.
 *
 * - The server picks a random salt and a secret number up to MAX_NUMBER and
 *   sends `challenge = SHA-256(salt ‖ number)` with an HMAC signature over
 *   the challenge and the salt's expiry.
 * - The browser finds the number by trying each one, which takes a person's
 *   browser a moment and makes bulk submissions expensive.
 * - The answer is accepted once: the signature must match, the challenge
 *   must not have expired, the number must hash to the challenge, and the
 *   same challenge is never accepted twice.
 */
final class ProofOfWork
{
    public const MAX_NUMBER = 50_000;

    private const TTL_SECONDS = 600;

    /** @return array{algorithm: string, challenge: string, maxnumber: int, salt: string, signature: string} */
    public function challenge(): array
    {
        $salt = bin2hex(random_bytes(12)).'?expires='.(time() + self::TTL_SECONDS);
        $challenge = hash('sha256', $salt.random_int(0, self::MAX_NUMBER));

        return ['algorithm' => 'SHA-256', 'challenge' => $challenge, 'maxnumber' => self::MAX_NUMBER, 'salt' => $salt, 'signature' => $this->sign($challenge, $salt)];
    }

    /** @param  mixed  $answer  {salt, number, challenge, signature} */
    public function verify(mixed $answer): bool
    {
        if (! is_array($answer)) {
            return false;
        }
        $salt = $answer['salt'] ?? null;
        $challenge = $answer['challenge'] ?? null;
        $signature = $answer['signature'] ?? null;
        $number = $answer['number'] ?? null;
        if (! is_string($salt) || ! is_string($challenge) || ! is_string($signature) || ! is_int($number) || $number < 0 || $number > self::MAX_NUMBER) {
            return false;
        }
        if (preg_match('/^[0-9a-f]{24}\?expires=(\d{10})$/', $salt, $m) !== 1 || (int) $m[1] < time()) {
            return false;
        }
        if (! hash_equals($this->sign($challenge, $salt), $signature) || ! hash_equals($challenge, hash('sha256', $salt.$number))) {
            return false;
        }

        // Each challenge is accepted once.
        return Cache::add('x-pow:'.$challenge, true, self::TTL_SECONDS);
    }

    private function sign(string $challenge, string $salt): string
    {
        return hash_hmac('sha256', $challenge.'|'.$salt, 'external-pow|'.(string) config('app.key'));
    }
}
