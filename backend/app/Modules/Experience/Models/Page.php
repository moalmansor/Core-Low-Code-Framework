<?php

declare(strict_types=1);

namespace App\Modules\Experience\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A custom page (specification §4.29): rich content, a link, a dashboard, a
 * report, or a form shown directly. Its name and its content are
 * translatable; it carries its own permission `page.{uuid}.view`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $application_id
 * @property string $key
 * @property string $type
 * @property string|null $url
 * @property int|null $dashboard_id
 * @property int|null $report_id
 * @property int|null $form_id
 * @property array<string, mixed>|null $layout
 * @property bool $is_published
 * @property string|null $published_at
 * @property string|null $deleted_at
 */
final class Page extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'content'];

    protected $table = 'pages';

    protected function casts(): array
    {
        return ['layout' => 'array', 'is_published' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'page';
    }
}
