<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Department;
use Illuminate\Validation\ValidationException;

/** Maps an API subject (type + uuid) to the internal id used by grants. */
final class SubjectResolver
{
    public static function id(string $type, string $uuid): int
    {
        $model = match ($type) {
            'role' => Role::class,
            'user' => User::class,
            'department' => Department::class,
            default => throw ValidationException::withMessages(['subject_type' => __('validation.in', ['attribute' => 'subject_type'])]),
        };
        $id = $model::query()->where('uuid', $uuid)->value('id');
        if ($id === null) {
            throw ValidationException::withMessages(['subject' => __('validation.exists', ['attribute' => 'subject'])]);
        }

        return (int) $id;
    }
}
