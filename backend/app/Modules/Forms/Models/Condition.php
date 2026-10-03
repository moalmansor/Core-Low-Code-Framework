<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A rule of the conditions engine (specification §4.7): a boolean expression
 * AST and the effects applied when it holds (and when it does not).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $form_id
 * @property string $owner_type
 * @property int $owner_id
 * @property string|null $name
 * @property array<string, mixed> $ast
 * @property list<array<string, mixed>> $effects
 * @property list<array<string, mixed>>|null $else_effects
 * @property string $evaluate_on
 * @property string $runtime
 * @property int $sort_order
 * @property bool $is_active
 */
final class Condition extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = ['form_id', 'owner_type', 'owner_id', 'name', 'ast', 'effects', 'else_effects', 'evaluate_on', 'runtime', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['ast' => 'array', 'effects' => 'array', 'else_effects' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
