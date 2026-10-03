<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores any JSON value (including scalars) as `{"v": value}`. SQL Server 2019's
 * ISJSON(), used by the JSON CHECK constraints, accepts only objects and arrays,
 * while MySQL also accepts scalars; the envelope behaves the same on both.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
final class JsonEnvelope implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) && array_key_exists('v', $decoded) ? $decoded['v'] : $decoded;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode(['v' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
