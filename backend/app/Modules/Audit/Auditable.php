<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete/restore of a model with field-level diffs
 * (specification §4.20). Models define `auditCategory()` and `auditType()`;
 * attributes in `$auditExclude` and the model's hidden attributes are masked.
 */
trait Auditable
{
    abstract public function auditCategory(): string;

    abstract public function auditType(): string;

    public static function bootAuditable(): void
    {
        static::created(static fn (self $m) => $m->writeAudit('created', $m->auditDiff(true)));
        static::updated(static function (self $m): void {
            $diff = $m->auditDiff(false);
            if ($diff !== []) {
                $m->writeAudit('updated', $diff);
            }
        });
        static::deleted(static fn (self $m) => $m->writeAudit(method_exists($m, 'isForceDeleting') && ! $m->isForceDeleting() ? 'deleted' : 'purged', null));
        if (method_exists(static::class, 'restored')) {
            static::restored(static fn (self $m) => $m->writeAudit('restored', null));
        }
    }

    /** @param list<array<string, mixed>>|null $changes */
    protected function writeAudit(string $action, ?array $changes): void
    {
        app(AuditWriter::class)->record(
            event: $this->auditType().'.'.$action,
            category: $this->auditCategory(),
            changes: $changes,
            objectType: $this->auditType(),
            objectId: (int) $this->getKey(),
        );
    }

    /** @return list<array{field_key: string, old: mixed, new: mixed}> */
    protected function auditDiff(bool $creating): array
    {
        $ignored = array_merge(['created_at', 'updated_at', 'created_by', 'updated_by'], $this->auditExclude ?? []);
        $hidden = $this->getHidden();
        $diff = [];
        $attributes = $creating ? $this->getAttributes() : $this->getChanges();
        foreach ($attributes as $key => $new) {
            if (in_array($key, $ignored, true)) {
                continue;
            }
            $old = $creating ? null : $this->getOriginal($key);
            $masked = in_array($key, $hidden, true);
            $diff[] = [
                'field_key' => $key,
                'old' => $masked && $old !== null ? '«masked»' : $this->auditValue($old),
                'new' => $masked && $new !== null ? '«masked»' : $this->auditValue($new),
            ];
        }

        return $diff;
    }

    private function auditValue(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s.u') : $value;
    }
}
