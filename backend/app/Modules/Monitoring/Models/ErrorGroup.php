<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Audit\AuditWriter;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $fingerprint
 * @property string $exception_class
 * @property string $message_sample
 * @property string|null $module
 * @property string $severity
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property int $occurrences
 * @property string $status
 * @property int|null $assignee_user_id
 * @property string|null $notes
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon|null $last_alerted_at
 */
final class ErrorGroup extends BaseModel
{
    use Auditable, BelongsToOrganization;

    protected $fillable = ['organization_id', 'fingerprint', 'exception_class', 'message_sample', 'module', 'severity', 'first_seen_at', 'last_seen_at', 'occurrences', 'status', 'assignee_user_id', 'notes', 'resolved_at', 'resolved_by', 'last_alerted_at'];

    /** Occurrence bookkeeping is not an administrative change. */
    protected array $auditExclude = ['last_seen_at', 'occurrences', 'message_sample', 'last_alerted_at', 'fingerprint', 'exception_class', 'module', 'severity', 'first_seen_at', 'organization_id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'last_alerted_at' => 'datetime', 'occurrences' => 'integer'];
    }

    /** @return HasMany<ErrorLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(ErrorLog::class, 'error_group_id');
    }

    public function auditCategory(): string
    {
        return 'operations';
    }

    public function auditType(): string
    {
        return 'error_group';
    }

    protected function writeAudit(string $action, ?array $changes): void
    {
        // Groups are created by the reporter, not by people; only audit edits.
        if ($action === 'updated') {
            app(AuditWriter::class)->record('error_group.updated', 'operations', $changes, 'error_group', (int) $this->getKey());
        }
    }
}
