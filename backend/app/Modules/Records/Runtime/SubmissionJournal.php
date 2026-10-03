<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Submission journal (specification §4.2, architecture §19.6): every
 * submission is recorded in its own committed transaction before processing;
 * the idempotency key makes retries safe. Payload values of encrypted or
 * sensitive fields are stored encrypted; errors are masked.
 */
final class SubmissionJournal
{
    public function __construct(private readonly CorrelationId $correlation, private readonly TenantContext $tenant) {}

    /**
     * Opens (or re-opens for retry) the journal entry of a submission.
     *
     * @param  array<string, mixed>  $payload
     * @return array{id: int, uuid: string, state: string, record_id: int|null}
     */
    public function open(string $key, int $formId, int $versionId, string $operation, string $source, ?int $userId, array $payload, ?int $expectedRowVersion, ?int $recordId): array
    {
        return DB::transaction(function () use ($key, $formId, $versionId, $operation, $source, $userId, $payload, $expectedRowVersion, $recordId): array {
            $existing = DB::table('submission_journal')->where('idempotency_key', $key)->first();
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            if ($existing !== null) {
                if ((int) $existing->form_id !== $formId || (int) $existing->organization_id !== $this->tenant->organizationId()
                    || $existing->operation !== $operation || (int) ($existing->user_id ?? 0) !== (int) ($userId ?? 0)
                    || ($recordId !== null && $existing->record_id !== null && (int) $existing->record_id !== $recordId)) {
                    return ['id' => (int) $existing->id, 'uuid' => (string) $existing->uuid, 'state' => 'foreign', 'record_id' => null];
                }
                if (in_array($existing->status, ['processed', 'processing'], true)) {
                    return ['id' => (int) $existing->id, 'uuid' => strtolower((string) $existing->uuid), 'state' => $existing->status, 'record_id' => $existing->record_id === null ? null : (int) $existing->record_id];
                }
                DB::table('submission_journal')->where('id', $existing->id)->update([
                    'status' => 'processing', 'attempts' => (int) $existing->attempts + 1, 'updated_at' => $now,
                    'payload' => $this->seal($payload), 'expected_row_version' => $expectedRowVersion, 'correlation_id' => $this->correlation->get(),
                ]);

                return ['id' => (int) $existing->id, 'uuid' => strtolower((string) $existing->uuid), 'state' => 'retry', 'record_id' => $existing->record_id === null ? null : (int) $existing->record_id];
            }
            $uuid = (string) Str::uuid7();
            $id = (int) DB::table('submission_journal')->insertGetId([
                'uuid' => $uuid, 'organization_id' => $this->tenant->organizationId(), 'created_at' => $now, 'updated_at' => $now,
                'idempotency_key' => $key, 'form_id' => $formId, 'form_version_id' => $versionId, 'record_id' => $recordId,
                'operation' => $operation, 'source' => $source, 'user_id' => $userId, 'payload' => $this->seal($payload),
                'expected_row_version' => $expectedRowVersion, 'status' => 'processing', 'attempts' => 1,
                'correlation_id' => $this->correlation->get(),
            ]);

            return ['id' => $id, 'uuid' => $uuid, 'state' => 'new', 'record_id' => $recordId];
        });
    }

    public function processed(int $id, int $recordId): void
    {
        DB::table('submission_journal')->where('id', $id)->update(['status' => 'processed', 'record_id' => $recordId, 'processed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'error_message' => null]);
    }

    /** Rejected for a reason the user can fix (validation, conflict): the attempt is closed, a retry with the same key reprocesses it. */
    public function rejected(int $id, string $reason): void
    {
        DB::table('submission_journal')->where('id', $id)->update(['status' => 'failed', 'error_message' => mb_substr($reason, 0, 2000), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    public function failed(int $id, Throwable $e, ?int $errorLogId = null): void
    {
        DB::table('submission_journal')->where('id', $id)->update([
            'status' => 'failed',
            'error_message' => mb_substr(self::mask($e->getMessage()), 0, 2000),
            'error_trace' => mb_substr(self::mask($e->getTraceAsString()), 0, 60000),
            'error_log_id' => $errorLogId,
            'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function seal(array $payload): string
    {
        return json_encode(['sealed' => Crypt::encryptString((string) json_encode($payload, JSON_UNESCAPED_UNICODE))], JSON_THROW_ON_ERROR);
    }

    private static function mask(string $text): string
    {
        return (string) preg_replace(['/(password|secret|token)=\S+/i', '/\b\d{12,19}\b/'], ['$1=«masked»', '«masked»'], $text);
    }
}
