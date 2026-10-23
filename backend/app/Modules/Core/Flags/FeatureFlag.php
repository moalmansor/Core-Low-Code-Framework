<?php

declare(strict_types=1);

namespace App\Modules\Core\Flags;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A feature flag (specification §4.36, ADR-0062): releases one form, action,
 * page or capability to an audience before everyone. Its description is
 * translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property int|null $application_id
 * @property string $target_type
 * @property int|null $target_id
 * @property bool $is_enabled
 * @property array{roles?: list<string>, departments?: list<string>, users?: list<string>} $audience
 */
final class FeatureFlag extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['description'];

    protected $table = 'feature_flags';

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'audience' => 'array'];
    }

    public function translationType(): string
    {
        return 'feature_flag';
    }
}
