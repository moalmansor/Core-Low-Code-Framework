<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages;

use App\Modules\Identity\Models\User;

/**
 * One kind of object a configuration package carries (ADR-0052). Objects
 * keep their uuid across environments; the key may be changed on import.
 */
interface PackagePart
{
    public function type(): string;

    /**
     * What can be exported, for the export screen.
     *
     * @return list<array{uuid: string, key: string, label: string, note?: string|null}>
     */
    public function catalog(): array;

    /**
     * The object as it is here, or null when it does not exist. Throws a
     * ValidationException when it cannot be exported as it stands.
     *
     * @return array{uuid: string, key: string, label: string, content: array<string, mixed>, dependencies: list<string>}|null
     */
    public function export(string $uuid): ?array;

    public function exists(string $uuid): bool;

    /** Whether another object of this kind already uses the key. */
    public function keyTaken(string $key, string $uuid): bool;

    /**
     * Creates or updates the object from the package.
     *
     * @param  array{uuid: string, key: string, label: string, content: array<string, mixed>, dependencies: list<string>}  $object
     * @param  array<string, mixed>  $options  confirm_destructive and the like, from the resolution
     * @return list<string> notes for the report (things skipped)
     */
    public function apply(array $object, User $user, array $options): array;
}
