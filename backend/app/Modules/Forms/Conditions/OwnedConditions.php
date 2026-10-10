<?php

declare(strict_types=1);

namespace App\Modules\Forms\Conditions;

use App\Modules\Forms\Models\Condition;

/**
 * Conditions owned by an object outside the draft document (a transition, an
 * SLA rule, a justification, assignment or record access rule, a view panel).
 * The owner stores the condition id; the condition row holds the AST and is
 * replaced or removed together with the owner's setting.
 */
final class OwnedConditions
{
    /**
     * Stores `$ast` as the owner's condition and returns its id; a null AST
     * removes the existing one.
     *
     * @param  array<string, mixed>|null  $ast
     */
    public function put(?int $formId, string $ownerType, int $ownerId, ?array $ast, ?int $existingId): ?int
    {
        if ($ast === null) {
            if ($existingId !== null) {
                Condition::query()->whereKey($existingId)->delete();
            }

            return null;
        }
        $row = $existingId === null ? null : Condition::query()->find($existingId);
        $row ??= new Condition;
        $row->fill([
            'form_id' => $formId, 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'ast' => $ast,
            'effects' => [], 'else_effects' => [], 'evaluate_on' => 'always', 'runtime' => 'server_only', 'sort_order' => 0, 'is_active' => true,
        ]);
        $row->save();

        return $row->id;
    }

    /** @return array<string, mixed>|null */
    public function ast(?int $id): ?array
    {
        return $id === null ? null : Condition::query()->find($id)?->ast;
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function many(array $ids): array
    {
        $ids = array_values(array_filter($ids));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (Condition::query()->whereIn('id', $ids)->get(['id', 'ast']) as $c) {
            $out[$c->id] = $c->ast;
        }

        return $out;
    }

    public function forget(?int $id): void
    {
        if ($id !== null) {
            Condition::query()->whereKey($id)->delete();
        }
    }
}
