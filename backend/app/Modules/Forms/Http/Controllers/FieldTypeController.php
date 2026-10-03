<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Forms\FieldTypes\FieldTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/** The builder palette (architecture §14.4): every field and group type. */
final class FieldTypeController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => [
            'fields' => array_values(array_map(static fn ($t) => $t->toArray(), FieldTypeRegistry::all())),
            'groups' => array_map(static fn (string $g) => [
                'key' => $g,
                'data' => in_array($g, FieldTypeRegistry::DATA_GROUPS, true),
                'parents' => FieldTypeRegistry::GROUP_PARENTS[$g] ?? null,
                'children' => FieldTypeRegistry::EXCLUSIVE_CHILDREN[$g] ?? null,
            ], FieldTypeRegistry::GROUP_TYPES),
        ]]);
    }
}
