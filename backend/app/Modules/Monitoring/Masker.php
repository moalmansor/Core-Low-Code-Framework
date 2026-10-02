<?php

declare(strict_types=1);

namespace App\Modules\Monitoring;

/**
 * Removes sensitive data before anything is logged (specification §5:
 * "no sensitive data in logs or in user-facing errors").
 */
final class Masker
{
    private const SENSITIVE_KEYS = '/pass(word)?|secret|token|api[_-]?key|authorization|cookie|session|credential|two_factor|recovery|otp|code$|private/i';

    private const SENSITIVE_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-xsrf-token', 'x-csrf-token', 'proxy-authorization'];

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function payload(array $data, int $depth = 0): array
    {
        if ($depth > 6) {
            return ['«truncated»'];
        }
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, $key) === 1) {
                $out[$key] = '«masked»';
            } elseif (is_array($value)) {
                $out[$key] = $this->payload($value, $depth + 1);
            } elseif (is_string($value)) {
                $out[$key] = mb_strlen($value) > 2000 ? mb_substr($value, 0, 2000).'…' : $value;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $values) {
            $out[$name] = in_array(strtolower($name), self::SENSITIVE_HEADERS, true) ? '«masked»' : implode(', ', array_map('strval', $values));
        }

        return $out;
    }

    /** Masks query-string values, keeping keys. */
    public function url(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['query'])) {
            return $url;
        }
        parse_str($parts['query'], $query);
        $masked = http_build_query(array_map(static fn (): string => '«masked»', $query));

        return strtok($url, '?').'?'.$masked;
    }

    /** Masks e-mail addresses and long digit runs in free text (messages, traces). */
    public function text(string $text): string
    {
        $text = (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '«email»', $text);

        return (string) preg_replace('/\b\d{9,}\b/', '«number»', $text);
    }
}
