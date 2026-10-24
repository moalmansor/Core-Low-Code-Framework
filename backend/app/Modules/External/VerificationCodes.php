<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\Notifications\Channels\ChannelFailure;
use App\Modules\Notifications\Channels\ChannelProviders;
use App\Modules\Notifications\EmailQueue;
use App\Modules\Notifications\Models\NotificationChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * One-time codes that prove a visitor controls an email address or a mobile
 * number (specification §4.32, ADR-0058).
 *
 * - A code has six digits, lives ten minutes and allows five tries; only
 *   its HMAC is kept, in the cache.
 * - Emails go through the email queue like every email the system sends.
 *   Text messages go straight to the default SMS channel, so the code is
 *   not kept in a delivery log; a refused message is reported to the
 *   visitor at once.
 * - Sending is limited per address and per visitor address.
 */
final class VerificationCodes
{
    private const TTL_SECONDS = 600;

    private const MAX_TRIES = 5;

    public function __construct(private readonly EmailQueue $emails, private readonly ChannelProviders $channels) {}

    /** The address in its stored form, or null when it is not a valid one. */
    public function normalize(string $method, mixed $to): ?string
    {
        if (! is_string($to)) {
            return null;
        }
        $to = trim($to);
        if ($method === 'email') {
            $to = mb_strtolower($to);

            return Validator::make(['to' => $to], ['to' => ['required', 'email:rfc', 'max:255']])->passes() ? $to : null;
        }
        $to = (string) preg_replace('/[\s().-]/', '', $to);

        return preg_match('/^\+[1-9]\d{6,14}$/', $to) === 1 ? $to : null;
    }

    public function smsAvailable(): bool
    {
        return $this->smsChannel() !== null;
    }

    /**
     * Sends a code. Returns null when sent, else the reason it was not.
     *
     * @param  string  $scope  what the code is for, e.g. "form:12"
     */
    public function send(string $scope, string $method, string $to, string $ip, string $locale): ?string
    {
        $limits = ['x-code-to:'.hash('sha256', $scope.'|'.$to) => 3, 'x-code-ip:'.hash('sha256', $scope.'|'.$ip) => 10];
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return __('external.code_too_many');
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, self::TTL_SECONDS);
        }
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $text = __('external.code_text', ['code' => $code, 'minutes' => intdiv(self::TTL_SECONDS, 60)], $locale);
        if ($method === 'email') {
            $this->emails->enqueue([
                'source' => 'system', 'locale' => $locale, 'to' => [$to], 'subject' => __('external.code_subject', [], $locale),
                'html' => '<p dir="'.(str_starts_with($locale, 'ar') ? 'rtl' : 'ltr').'">'.e($text).'</p>',
            ]);
        } else {
            $channel = $this->smsChannel();
            if ($channel === null) {
                return __('external.sms_unavailable');
            }
            try {
                $this->channels->send('sms', $channel->provider, (array) $channel->config, ['to' => $to, 'title' => $text, 'text' => '', 'link' => '']);
            } catch (ChannelFailure) {
                return __('external.sms_failed');
            } catch (Throwable $e) {
                report($e);

                return __('external.sms_failed');
            }
        }
        Cache::put($this->key($scope, $to), ['hash' => $this->hash($scope, $to, $code), 'tries' => 0], self::TTL_SECONDS);

        return null;
    }

    /** Checks a code and uses it up when it matches. */
    public function check(string $scope, string $to, mixed $code): bool
    {
        $key = $this->key($scope, $to);
        $entry = Cache::get($key);
        if (! is_array($entry) || ! is_string($code) || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }
        if (hash_equals((string) $entry['hash'], $this->hash($scope, $to, $code))) {
            Cache::forget($key);

            return true;
        }
        $entry['tries'] = (int) $entry['tries'] + 1;
        $entry['tries'] >= self::MAX_TRIES ? Cache::forget($key) : Cache::put($key, $entry, self::TTL_SECONDS);

        return false;
    }

    private function smsChannel(): ?NotificationChannel
    {
        return NotificationChannel::query()->where('type', 'sms')->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }

    private function key(string $scope, string $to): string
    {
        return 'x-code:'.hash('sha256', $scope.'|'.$to);
    }

    private function hash(string $scope, string $to, string $code): string
    {
        return hash_hmac('sha256', $scope.'|'.$to.'|'.$code, 'external-code|'.(string) config('app.key'));
    }
}
