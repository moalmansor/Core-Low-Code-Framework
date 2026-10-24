<?php

declare(strict_types=1);

namespace App\Modules\Experience\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * Help an administrator wrote (specification §4.37, ADR-0064): for a form, a
 * field, a group, a page, an application or an admin area, shown as a
 * tooltip, in a side panel, or as a page of its own. Title and body are
 * translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property string $target_type
 * @property int|null $target_id
 * @property string $display
 * @property bool $is_published
 * @property int $sort_order
 */
final class HelpContent extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $table = 'help_content';

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function translationType(): string
    {
        return 'help_content';
    }
}
