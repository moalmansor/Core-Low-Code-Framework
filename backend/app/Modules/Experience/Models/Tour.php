<?php

declare(strict_types=1);

namespace App\Modules\Experience\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A guided tour (specification §4.37, ADR-0065): steps that point at parts of
 * a form or a screen, shown the first time someone opens it or when they ask.
 * The name and each step's title and text are translatable
 * (`steps.<step_key>.title`, `steps.<step_key>.body`).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $form_id
 * @property string|null $route_key
 * @property list<int>|null $audience_role_ids
 * @property string $trigger
 * @property list<array{step_key: string, target_selector: string|null, placement: string}> $steps
 * @property int $version
 * @property bool $is_active
 */
final class Tour extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'tours';

    protected function casts(): array
    {
        return ['audience_role_ids' => 'array', 'steps' => 'array', 'version' => 'integer', 'is_active' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'tour';
    }
}
