<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Numbers\Decimal;
use App\Expressions\Values\Value;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the `@user` scope of expression contexts (expression-language.md §2):
 * id, name, email, role keys, department code and the codes of its ancestors,
 * locale, and the admin-defined user attributes.
 */
final class ExpressionContext
{
    /** @var array<int, array<string, mixed>> */
    private array $memo = [];

    /** @return array{id: int, name: string, email: string|null, roles: list<string>, department: string|null, departments: list<string>, attributes: array<string, Value>, locale: string|null} */
    public function user(User $user): array
    {
        return $this->memo[$user->id] ??= $this->build($user);
    }

    /** @return array{id: int, name: string, email: string|null, roles: list<string>, department: string|null, departments: list<string>, attributes: array<string, Value>, locale: string|null} */
    private function build(User $user): array
    {
        $department = $user->department_id === null ? null : DB::table('departments')->where('id', $user->department_id)->value('code');
        $ancestors = $user->department_id === null ? [] : DB::table('department_closure')
            ->join('departments', 'departments.id', '=', 'department_closure.ancestor_id')
            ->where('department_closure.descendant_id', $user->department_id)
            ->orderBy('department_closure.depth')
            ->pluck('departments.code')->map(static fn ($c) => (string) $c)->all();
        $attributes = [];
        foreach ($user->attributes ?? [] as $key => $value) {
            $attributes[(string) $key] = match (true) {
                is_bool($value) => Value::bool($value),
                is_int($value), is_float($value) => ($d = Decimal::parse((string) $value)) !== null ? Value::number($d) : Value::null(),
                is_string($value) => Value::text($value),
                default => Value::null(),
            };
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => $user->email,
            'roles' => $user->activeRoles()->pluck('roles.key')->map(static fn ($k) => (string) $k)->all(),
            'department' => $department === null ? null : (string) $department,
            'departments' => $ancestors,
            'attributes' => $attributes,
            'locale' => app()->getLocale(),
        ];
    }
}
