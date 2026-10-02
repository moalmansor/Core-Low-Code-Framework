<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Appends hash-chained audit entries (architecture §19.15). Each entry belongs
 * to one of 16 chains; the chain head row is locked inside the writing
 * transaction so sequence numbers and hashes are gap-free and ordered.
 */
final class AuditWriter
{
    public const CHAINS = 16;

    /** Attribute names whose values are always masked in audit entries. */
    private const SENSITIVE = ['password', 'password_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'token', 'secret', 'client_secret', 'bind_password', 'encrypted_value', 'api_key'];

    public function __construct(
        private readonly DatabaseDriver $driver,
        private readonly TenantContext $tenant,
        private readonly CorrelationId $correlation,
    ) {}

    /**
     * @param  list<array{field_key: string, old: mixed, new: mixed}>|null  $changes
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        string $event,
        string $category,
        ?array $changes = null,
        ?string $objectType = null,
        ?int $objectId = null,
        ?array $meta = null,
        ?int $actorUserId = null,
        ?int $subjectUserId = null,
    ): int {
        $request = app()->bound('request') ? app(Request::class) : null;
        $entry = [
            'occurred_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            'organization_id' => $this->tenant->organizationId(),
            'event' => $event,
            'category' => $category,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'form_id' => null,
            'record_id' => null,
            'changes' => $changes === null ? null : $this->mask($changes),
            'actor_user_id' => $actorUserId ?? Auth::id(),
            'subject_user_id' => $subjectUserId,
            'on_behalf_of_user_id' => null,
            'external_user_id' => null,
            'impersonation_session_id' => null,
            'justification_id' => null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 512),
            'correlation_id' => $this->correlation->get(),
            'meta' => $meta,
        ];
        $chain = $this->chainFor($objectType, $objectId);

        return DB::transaction(function () use ($entry, $chain): int {
            $head = $this->driver->lockForUpdate(DB::table('audit_chain_heads')->where('chain_id', $chain))->first();
            $seq = (int) $head->last_seq + 1;
            $entry['chain_id'] = $chain;
            $entry['chain_seq'] = $seq;
            $entry['prev_hash'] = (string) $head->last_hash;
            $entry['hash'] = self::hash($entry['prev_hash'], $entry);
            $row = $entry;
            $row['changes'] = $entry['changes'] === null ? null : json_encode($entry['changes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $row['meta'] = $entry['meta'] === null ? null : json_encode($entry['meta'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $id = (int) DB::table('audit_logs')->insertGetId($row);
            DB::table('audit_chain_heads')->where('chain_id', $chain)->update([
                'last_seq' => $seq,
                'last_hash' => $entry['hash'],
                'updated_at' => $entry['occurred_at'],
            ]);

            return $id;
        });
    }

    /**
     * SHA-256(prev_hash ‖ canonical JSON of the entry). Canonical JSON sorts keys
     * recursively and excludes the id and the hash fields themselves.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function hash(string $prevHash, array $entry): string
    {
        unset($entry['id'], $entry['hash'], $entry['prev_hash']);

        return hash('sha256', $prevHash.self::canonical($entry));
    }

    /** @param array<string, mixed> $value */
    public static function canonical(array $value): string
    {
        $sort = static function (mixed $v) use (&$sort): mixed {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($sort, $v);
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    private function mask(array $changes): array
    {
        return array_map(static function (array $change): array {
            $key = strtolower((string) ($change['field_key'] ?? ''));
            $leaf = str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key;
            if (in_array($leaf, self::SENSITIVE, true)) {
                $change['old'] = $change['old'] === null ? null : '«masked»';
                $change['new'] = $change['new'] === null ? null : '«masked»';
            }

            return $change;
        }, $changes);
    }

    private function chainFor(?string $objectType, ?int $objectId): int
    {
        $key = $objectType !== null && $objectId !== null ? $objectType.':'.$objectId : $this->correlation->get();

        return (int) (crc32($key) % self::CHAINS);
    }
}
