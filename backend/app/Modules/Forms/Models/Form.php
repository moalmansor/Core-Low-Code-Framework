<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A form or collection (specification §4.3–§4.10). Its draft lives in the
 * working tables (groups, fields, options, conditions, relations — ADR-0018);
 * each publish freezes an immutable `form_versions` snapshot.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $application_id
 * @property string $kind
 * @property string $key
 * @property string $table_name
 * @property string $binding_mode
 * @property string $state
 * @property int|null $current_version_id
 * @property int $draft_version_number
 * @property Carbon|null $draft_updated_at
 * @property int|null $draft_updated_by
 * @property string|null $icon
 * @property string $data_sharing
 * @property bool $workflow_enabled
 * @property int|null $numbering_sequence_id
 * @property int|null $business_calendar_id
 * @property array<string, mixed>|null $title_template
 * @property array<string, mixed> $settings
 * @property int|null $blueprint_instance_id
 * @property int $record_count_cache
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Form extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, SoftDeletes, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'submit_button_label'];

    /** @var list<string> */
    protected array $auditExclude = ['draft_updated_at', 'draft_updated_by', 'record_count_cache'];

    protected $fillable = [
        'application_id', 'kind', 'key', 'table_name', 'binding_mode', 'state', 'current_version_id',
        'draft_version_number', 'draft_updated_at', 'draft_updated_by', 'icon', 'data_sharing', 'workflow_enabled',
        'numbering_sequence_id', 'business_calendar_id', 'title_template', 'settings', 'blueprint_instance_id', 'record_count_cache',
    ];

    protected function casts(): array
    {
        return [
            'draft_updated_at' => 'datetime',
            'workflow_enabled' => 'boolean',
            'title_template' => 'array',
            'settings' => 'array',
            'draft_version_number' => 'integer',
            'record_count_cache' => 'integer',
        ];
    }

    public function isCollection(): bool
    {
        return $this->kind === 'collection';
    }

    public function isPublished(): bool
    {
        return $this->current_version_id !== null;
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'current_version_id');
    }

    /** @return HasMany<FormVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /** @return HasMany<FieldGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(FieldGroup::class);
    }

    /** @return HasMany<Field, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    /** @return HasMany<Relation, $this> */
    public function relations(): HasMany
    {
        return $this->hasMany(Relation::class, 'source_form_id');
    }

    /** @return HasMany<Condition, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(Condition::class);
    }

    /** @return HasOne<Collection, $this> */
    public function collection(): HasOne
    {
        return $this->hasOne(Collection::class);
    }

    public function translationType(): string
    {
        return 'form';
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'form';
    }
}
