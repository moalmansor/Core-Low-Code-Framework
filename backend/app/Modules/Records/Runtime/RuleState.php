<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

/**
 * Effects of the conditions for one evaluation: per element flags (hidden,
 * disabled, readOnly, required), for repeater fields per row, plus messages
 * and submit blocks.
 */
final class RuleState
{
    /** @var array<string, array<string, bool>> target uuid (or uuid#rowKey#index) => flags */
    public array $flags = [];

    /** @var list<array<string, mixed>> */
    public array $messages = [];

    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    /** @param  array{type: string, uuid: string}|null  $target */
    public function set(?array $target, string $flag, bool $on, ?array $row): void
    {
        if ($target === null) {
            return;
        }
        $this->flags[$this->key($target['uuid'], $row)][$flag] = $on;
    }

    /** @param  array{0: string, 1: int}|null  $row */
    public function flag(string $uuid, string $flag, ?array $row = null): bool
    {
        if ($row !== null && isset($this->flags[$this->key($uuid, $row)][$flag])) {
            return $this->flags[$this->key($uuid, $row)][$flag];
        }

        return $this->flags[$uuid][$flag] ?? false;
    }

    /** @return array<string, array<string, bool>> */
    public function toArray(): array
    {
        return $this->flags;
    }

    private function key(string $uuid, ?array $row): string
    {
        return $row === null ? $uuid : $uuid.'#'.$row[0].'#'.$row[1];
    }
}
