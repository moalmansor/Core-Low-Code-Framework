<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Sdk;

use App\Infrastructure\Egress\EgressGateway;
use LogicException;

/**
 * What a server hook sees and may do (specification §4.19, ADR-0051).
 *
 * - Values are by field key, as the record pipeline holds them.
 * - `set()` is allowed at onLoad, beforeValidate and beforeSave; values set
 *   before saving are validated again with the rest of the record.
 * - `error()` adds a field error at beforeValidate, afterValidate and
 *   beforeSave; `fail()` refuses the whole operation with a message.
 * - `http()` calls out through the egress gateway only; in a test run it
 *   sends nothing and answers 599.
 */
final class HookContext
{
    private const SETTABLE = ['onLoad', 'beforeValidate', 'beforeSave'];

    private const ERRORS = ['beforeValidate', 'afterValidate', 'beforeSave'];

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var list<string> */
    private array $logs = [];

    /** @var list<string> */
    private array $changed = [];

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $old
     * @param  array{uuid: string, name: string, email: string|null, roles: list<string>}  $user
     * @param  array<string, mixed>  $extra  transition {key, from, to} or action {key}
     */
    public function __construct(
        private readonly string $point,
        private readonly string $form,
        private readonly ?string $record,
        private array $values,
        private readonly ?array $old,
        private readonly array $user,
        private readonly array $extra = [],
        private readonly bool $test = false,
        private readonly ?Records $records = null,
    ) {}

    public function point(): string
    {
        return $this->point;
    }

    /** The form's key. */
    public function form(): string
    {
        return $this->form;
    }

    /** The record's id, or null before it is created. */
    public function record(): ?string
    {
        return $this->record;
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

    public function get(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    /** The value before this change (null on create). */
    public function old(string $field): mixed
    {
        return $this->old[$field] ?? null;
    }

    /** @return array<string, mixed> transition {key, from, to} or action {key} */
    public function extra(): array
    {
        return $this->extra;
    }

    public function set(string $field, mixed $value): void
    {
        if (! in_array($this->point, self::SETTABLE, true)) {
            throw new LogicException("Values cannot be set at {$this->point}.");
        }
        if (! array_key_exists($field, $this->values) && $this->point === 'onLoad') {
            return;
        }
        $this->values[$field] = $value;
        $this->changed[] = $field;
    }

    public function error(string $field, string $message): void
    {
        if (! in_array($this->point, self::ERRORS, true)) {
            throw new LogicException("Field errors cannot be added at {$this->point}.");
        }
        $this->errors[$field][] = mb_substr($message, 0, 500);
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

    /**
     * An outbound call through the egress gateway.
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

    /** Records of other forms, read as the person this hook acts for. */
    public function records(): Records
    {
        return $this->records ?? throw new LogicException('Records are not available here.');
    }

    /** @internal @return array<string, mixed> */
    public function values(): array
    {
        return $this->values;
    }

    /** @internal @return list<string> */
    public function changed(): array
    {
        return array_values(array_unique($this->changed));
    }

    /** @internal @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @internal @return list<string> */
    public function logs(): array
    {
        return $this->logs;
    }
}
