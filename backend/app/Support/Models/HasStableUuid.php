<?php

declare(strict_types=1);

namespace App\Support\Models;

use Illuminate\Support\Str;

/**
 * Assigns a time-ordered UUIDv7 to the `uuid` column on create (ADR-0003) and
 * resolves route bindings by uuid so internal ids never appear in URLs.
 */
trait HasStableUuid
{
    public static function bootHasStableUuid(): void
    {
        static::creating(static function (self $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid7());
            }
        });
    }

    /**
     * SQL Server returns UNIQUEIDENTIFIER values in upper case and MySQL stores
     * the canonical lower-case text: the API always shows lower case.
     */
    public function getUuidAttribute(?string $value): ?string
    {
        return $value === null ? null : strtolower($value);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
