<?php

declare(strict_types=1);

namespace App\Modules\Experience\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A visual theme (specification §4.28): colours for light and dark mode,
 * shape, density and fonts, sign-in page texts, scoped custom CSS and
 * artwork, for an organization or one application. Texts are translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $application_id
 * @property string $key
 * @property bool $is_default
 * @property bool $is_preset
 * @property array<string, mixed> $tokens
 * @property string|null $custom_css
 * @property string|null $custom_css_compiled
 * @property list<array<string, mixed>>|null $contrast_report
 * @property int $version
 */
final class Theme extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'login_welcome', 'support_contact', 'browser_title'];

    protected $table = 'themes';

    protected function casts(): array
    {
        return ['tokens' => 'array', 'contrast_report' => 'array', 'is_default' => 'boolean', 'is_preset' => 'boolean', 'version' => 'integer'];
    }

    public function translationType(): string
    {
        return 'theme';
    }
}
