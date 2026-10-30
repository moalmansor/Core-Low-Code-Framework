<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Core\Actors\ExternalActor;
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
    /** The import job whose rows are being submitted, recorded on each entry (architecture §19.5). */
    private ?int $importJobId = null;

    /** True while the keys being opened cannot exist yet (a parallel import batch, guarded by its batch row). */
    private bool $freshKeys = false;

    /**
     * Entries of fresh keys, written once their outcome is known (negative ids). They share the batch's
     * transaction, so the entry and the record it describes commit or roll back together.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $deferred = [];

    private int $deferredSeq = 0;

    public function __construct(private readonly CorrelationId $correlation, private readonly TenantContext $tenant) {}

    /**
     * Runs `$work` with every entry it opens linked to an import job. With
     * `$freshKeys` the caller vouches that none of the keys it opens exists
     * yet, so no entry is looked up first: a parallel import batch, whose
     * unique batch row is written in the same transaction before any of its
     * rows, and whose keys name the job and the row.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function forImportJob(int $jobId, callable $work, bool $freshKeys = false): mixed
    {
        [$previous, $previousFresh] = [$this->importJobId, $this->freshKeys];
        $this->importJobId = $jobId;
        $this->freshKeys = $freshKeys;
        try {
            return $work();
        } finally {
            [$this->importJobId, $this->freshKeys] = [$previous, $previousFresh];
            if ($freshKeys) {
                // An entry left open here belongs to a row whose exception escaped: its batch rolls back.
                $this->deferred = [];
            }
        }
    }

    /**
     * Opens (or re-opens for retry) the journal entry of a submission.
     *
     * @param  array<string, mixed>  $payload
     * @return array{id: int, uuid: string, state: string, record_id: int|null}
     */
    public function open(string $key, int $formId, int $versionId, string $operation, string $source, ?int $userId, array $payload, ?int $expectedRowVersion, ?int $recordId): array
    {
        $open = function () use ($key, $formId, $versionId, $operation, $source, $userId, $payload, $expectedRowVersion, $recordId): array {
            $existing = $this->freshKeys ? null : DB::table('submission_journal')->where('idempotency_key', $key)->first();
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
            $row = [
                'uuid' => $uuid, 'organization_id' => $this->tenant->organizationId(), 'created_at' => $now, 'updated_at' => $now,
                'idempotency_key' => $key, 'form_id' => $formId, 'form_version_id' => $versionId, 'record_id' => $recordId,
                'operation' => $operation, 'source' => $source, 'user_id' => $userId, 'payload' => $this->seal($payload),
                'expected_row_version' => $expectedRowVersion, 'status' => 'processing', 'attempts' => 1,
                'correlation_id' => $this->correlation->get(), 'import_job_id' => $this->importJobId, 'external_user_id' => app(ExternalActor::class)->id,
            ];
            if ($this->freshKeys) {
                // Written with its outcome: one insert instead of an insert and an update.
                $id = -(++$this->deferredSeq);
                $this->deferred[$id] = $row;

                return ['id' => $id, 'uuid' => $uuid, 'state' => 'new', 'record_id' => $recordId];
            }
            $id = (int) DB::table('submission_journal')->insertGetId($row);

            return ['id' => $id, 'uuid' => $uuid, 'state' => 'new', 'record_id' => $recordId];
        };

        // A fresh key has no read to keep consistent with its write, so no transaction of its own.
        return $this->freshKeys ? $open() : DB::transaction($open);
    }

    public function processed(int $id, int $recordId): void
    {
        $this->close($id, ['status' => 'processed', 'record_id' => $recordId, 'processed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'error_message' => null]);
    }

    /** Rejected for a reason the user can fix (validation, conflict): the attempt is closed, a retry with the same key reprocesses it. */
    public function rejected(int $id, string $reason): void
    {
        $this->close($id, ['status' => 'failed', 'error_message' => mb_substr($reason, 0, 2000), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    public function failed(int $id, Throwable $e, ?int $errorLogId = null): void
    {
        $this->close($id, [
            'status' => 'failed',
            'error_message' => mb_substr(self::mask($e->getMessage()), 0, 2000),
            'error_trace' => mb_substr(self::mask($e->getTraceAsString()), 0, 60000),
            'error_log_id' => $errorLogId,
            'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** @param  array<string, mixed>  $outcome */
    private function close(int $id, array $outcome): void
    {
        if ($id < 0 && isset($this->deferred[$id])) {
            DB::table('submission_journal')->insert($outcome + $this->deferred[$id]);
            unset($this->deferred[$id]);

            return;
        }
        DB::table('submission_journal')->where('id', $id)->update($outcome);
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
