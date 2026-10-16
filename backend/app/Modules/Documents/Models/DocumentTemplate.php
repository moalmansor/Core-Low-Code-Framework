<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * A document template (specification §4.17): an uploaded Word file with
 * placeholders (one per language), or a design of blocks, producing DOCX or
 * PDF from a record.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property string $type docx | html
 * @property int|null $form_id
 * @property int|null $file_id
 * @property array<string, string>|null $locale_files
 * @property array<string, mixed>|null $design
 * @property list<string> $output_formats
 * @property array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}} $paper
 * @property list<string> $placeholders_detected
 * @property bool $is_active
 */
final class DocumentTemplate extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'html'];

    protected $table = 'document_templates';

    protected $fillable = ['key', 'type', 'form_id', 'file_id', 'locale_files', 'design', 'output_formats', 'paper', 'placeholders_detected', 'is_active'];

    protected function casts(): array
    {
        return ['locale_files' => 'array', 'design' => 'array', 'output_formats' => 'array', 'paper' => 'array', 'placeholders_detected' => 'array', 'is_active' => 'boolean'];
    }

    public function translationType(): string
    {
        return 'document_template';
    }
}
