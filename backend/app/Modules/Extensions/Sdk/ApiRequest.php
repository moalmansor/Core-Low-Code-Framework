<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Sdk;

use App\Infrastructure\Egress\EgressGateway;

/** A call to a custom API endpoint: method, query, JSON body and the signed-in user. */
final class ApiRequest
{
    /** @var list<string> */
    private array $logs = [];

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array{uuid: string, name: string, email: string|null, roles: list<string>}  $user
     */
    public function __construct(
        private readonly string $method,
        private readonly array $query,
        private readonly array $body,
        private readonly array $user,
        private readonly Records $records,
        private readonly bool $test = false,
    ) {}

    public function method(): string
    {
        return $this->method;
    }

    public function query(string $key): mixed
    {
        return $this->query[$key] ?? null;
    }

    public function input(string $key): mixed
    {
        return $this->body[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    /** @return array{uuid: string, name: string, email: string|null, roles: list<string>} */
    public function user(): array
    {
        return $this->user;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function fail(string $message): never
    {
        throw new HookFailed(mb_substr($message, 0, 500));
    }

    public function log(string $message): void
    {
        if (count($this->logs) < 100) {
            $this->logs[] = mb_substr($message, 0, 1000);
        }
    }

    /** Records, read as the signed-in person. */
    public function records(): Records
    {
        return $this->records;
    }

    /**
     * An outbound call through the egress gateway; in a test run nothing is sent and the answer is 599.
     *
     * @param  array{headers?: array<string, string>, json?: mixed, body?: string, query?: array<string, scalar>}  $options
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function http(string $method, string $url, array $options = []): array
    {
        if ($this->test) {
            $this->log("HTTP {$method} {$url} (not sent in a test run)");

            return ['status' => 599, 'headers' => [], 'body' => ''];
        }
        $opts = array_intersect_key($options, array_flip(['headers', 'json', 'body', 'query']));
        $response = app(EgressGateway::class)->request(strtoupper($method), $url, $opts, 10, 1_048_576);

        return ['status' => $response->getStatusCode(), 'headers' => array_map(static fn ($v) => implode(', ', $v), $response->getHeaders()), 'body' => (string) $response->getBody()];
    }

    /** @internal @return list<string> */
    public function logs(): array
    {
        return $this->logs;
    }
}
