<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An email template (specification §4.16): a visual design of blocks with
 * placeholders, conditional blocks and repeater tables, and a subject and
 * preheader per language.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property int|null $application_id
 * @property int|null $form_id
 * @property array<string, mixed> $design
 * @property bool $is_active
 */
final class EmailTemplate extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['subject', 'body_html', 'preheader'];

    protected $table = 'email_templates';

    protected $fillable = ['key', 'application_id', 'form_id', 'design', 'is_active'];

    protected function casts(): array
    {
        return ['design' => 'array', 'is_active' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'email_template';
    }
}
