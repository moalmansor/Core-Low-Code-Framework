<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\EgressViolation;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The providers an SMS or messaging channel can use (specification §4.33,
 * architecture §19.21, ADR-0056), and how each one sends. Every request goes
 * through the egress gateway, so the provider's host must be on the
 * organization's allowlist.
 *
 *  - sms/twilio: the Twilio Messages API, form-encoded, with basic auth.
 *  - sms/http, messaging/http: any HTTPS endpoint; the body is a JSON
 *    template whose {{to}}, {{title}}, {{text}} and {{link}} are filled in
 *    as JSON strings, so a value can never change the template's structure.
 *  - messaging/slack, messaging/teams: an incoming-webhook address, posting
 *    one message to the conversation it belongs to.
 *
 * SMS goes to each recipient's mobile number; a messaging channel posts once
 * to its conversation (or the address set in "to" for the HTTP provider).
 */
final class ChannelProviders
{
    public const TYPES = ['sms', 'messaging'];

    /**
     * Each provider's settings: name => [required, secret].
     *
     * @var array<string, array<string, array<string, array{0: bool, 1: bool}>>>
     */
    public const PROVIDERS = [
        'sms' => [
            'twilio' => ['account_sid' => [true, false], 'auth_token' => [true, true], 'from' => [true, false]],
            'http' => ['url' => [true, false], 'method' => [false, false], 'body' => [true, false], 'header_name' => [false, false], 'header_value' => [false, true]],
        ],
        'messaging' => [
            'slack' => ['webhook_url' => [true, true]],
            'teams' => ['webhook_url' => [true, true]],
            'http' => ['url' => [true, false], 'method' => [false, false], 'body' => [true, false], 'header_name' => [false, false], 'header_value' => [false, true], 'to' => [false, false]],
        ],
    ];

    public const MAX_TEXT = 1600;

    private const TWILIO = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(private readonly EgressGateway $egress) {}

    public static function isPerRecipient(string $type): bool
    {
        return $type === 'sms';
    }

    /**
     * Checks and normalizes a provider's settings. A secret left empty keeps
     * the one already saved.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $saved
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function normalize(string $type, string $provider, array $config, array $saved): array
    {
        $fields = self::PROVIDERS[$type][$provider] ?? null;
        if ($fields === null) {
            throw ValidationException::withMessages(['provider' => __('notifications.channel.bad_provider')]);
        }
        $out = [];
        $errors = [];
        foreach ($fields as $name => [$required, $secret]) {
            $value = trim((string) ($config[$name] ?? ''));
            if ($value === '' && $secret && isset($saved[$name])) {
                $value = (string) $saved[$name];
            }
            if ($value === '') {
                if ($required) {
                    $errors["config.{$name}"][] = __('notifications.channel.required');
                }

                continue;
            }
            $out[$name] = mb_substr($value, 0, $name === 'body' ? 4000 : 2048);
        }
        foreach (['url', 'webhook_url'] as $u) {
            if (isset($out[$u]) && ! $this->allowedUrl($out[$u])) {
                $errors["config.{$u}"][] = __('notifications.channel.bad_url');
            }
        }
        if (isset($out['method']) && ! in_array(strtoupper($out['method']), ['POST', 'PUT'], true)) {
            $errors['config.method'][] = __('notifications.channel.bad_method');
        }
        if (isset($out['method'])) {
            $out['method'] = strtoupper($out['method']);
        }
        if (isset($out['body']) && json_decode(self::fill($out['body'], ['to' => 'x', 'title' => 'x', 'text' => 'x', 'link' => 'x'])) === null) {
            $errors['config.body'][] = __('notifications.channel.bad_body');
        }
        if (isset($out['header_name']) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $out['header_name']) !== 1) {
            $errors['config.header_name'][] = __('notifications.channel.bad_header');
        }
        if ($provider === 'twilio' && isset($out['account_sid']) && preg_match('/^AC[0-9a-fA-F]{32}$/', $out['account_sid']) !== 1) {
            $errors['config.account_sid'][] = __('notifications.channel.bad_sid');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * The settings as shown to an admin: secrets never leave the server, only whether one is set.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function masked(string $type, string $provider, array $config): array
    {
        $out = [];
        foreach (self::PROVIDERS[$type][$provider] ?? [] as $name => [, $secret]) {
            $out[$name] = $secret ? null : ($config[$name] ?? null);
            if ($secret) {
                $out[$name.'_set'] = isset($config[$name]) && $config[$name] !== '';
            }
        }

        return $out;
    }

    /**
     * Sends one message. Returns the provider's answer for the log; throws
     * ChannelFailure when it was refused (retryable or not).
     *
     * @param  array<string, string>  $config
     * @param  array{to: string, title: string, text: string, link: string}  $message
     */
    public function send(string $type, string $provider, array $config, array $message): string
    {
        $text = mb_substr(trim($message['title'].($message['text'] !== '' ? "\n".$message['text'] : '').($message['link'] !== '' ? "\n".$message['link'] : '')), 0, self::MAX_TEXT);
        [$method, $url, $options] = match ("{$type}/{$provider}") {
            'sms/twilio' => ['POST', sprintf(self::TWILIO, rawurlencode($config['account_sid'])), [
                'auth' => [$config['account_sid'], $config['auth_token']],
                'form_params' => ['To' => $message['to'], 'From' => $config['from'], 'Body' => $text],
            ]],
            'messaging/slack' => ['POST', $config['webhook_url'], ['json' => ['text' => $text]]],
            'messaging/teams' => ['POST', $config['webhook_url'], ['json' => ['text' => str_replace("\n", "\n\n", $text)]]],
            'sms/http', 'messaging/http' => [$config['method'] ?? 'POST', $config['url'], [
                'headers' => ['Content-Type' => 'application/json'] + (isset($config['header_name'], $config['header_value']) ? [$config['header_name'] => $config['header_value']] : []),
                'body' => self::fill($config['body'], ['to' => $message['to'], 'title' => $message['title'], 'text' => $text, 'link' => $message['link']]),
            ]],
            default => throw new ChannelFailure(__('notifications.channel.bad_provider'), false),
        };
        try {
            $response = $this->egress->request($method, $url, $options, 20, 262_144);
        } catch (EgressViolation $e) {
            throw new ChannelFailure($e->getMessage(), false);
        } catch (Throwable $e) {
            throw new ChannelFailure(mb_substr($e->getMessage(), 0, 500), true);
        }
        $status = $response->getStatusCode();
        $body = mb_strcut((string) $response->getBody(), 0, 2000);
        if ($status >= 300) {
            throw new ChannelFailure(__('notifications.channel.refused', ['status' => $status]).($body === '' ? '' : ': '.mb_substr($body, 0, 300)), $status >= 500 || $status === 429);
        }

        return 'HTTP '.$status;
    }

    /** Fills a JSON body template: each placeholder becomes a JSON string (quotes included). @param  array<string, string>  $values */
    public static function fill(string $template, array $values): string
    {
        return (string) preg_replace_callback('/"?\{\{\s*(to|title|text|link)\s*\}\}"?/', static fn (array $m) => (string) json_encode($values[$m[1]] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $template);
    }

    private function allowedUrl(string $url): bool
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }
        try {
            $this->egress->permits(new Uri($url));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
