<?php

declare(strict_types=1);

namespace App\Modules\Operations;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\DataQuality\DuplicateGuard;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Failed submissions (specification §4.2, ADR-0045): saves that broke for a
 * reason other than the person's input stay in the submission journal with
 * the payload, the error and its correlation ID. An operator retries them
 * (single or bulk) — through the record pipeline, as the person who
 * submitted them and with the same idempotency key, so a record is never
 * created twice and nothing bypasses their permissions or the record's
 * version — edits the payload first, or discards them with a reason.
 * Sensitive and encrypted values are never shown.
 */
final class FailedSubmissions
{
    public const MASK = '«masked»';

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordPipeline $pipeline,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    /** Saves that failed in the system (a rejected input is answered to the person and is not listed). */
    public function openCount(): int
    {
        return DB::table('submission_journal')->where('status', 'failed')->whereNotNull('error_trace')->count();
    }

    /**
     * @param  array{status?: string|null, form?: string|null, page?: int}  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters): array
    {
        $q = DB::table('submission_journal');
        match ($filters['status'] ?? 'failed') {
            'discarded' => $q->where('status', 'discarded'),
            'rejected' => $q->where('status', 'failed')->whereNull('error_trace'),
            default => $q->where('status', 'failed')->whereNotNull('error_trace'),
        };
        if (is_string($filters['form'] ?? null) && ($formId = DB::table('forms')->where('uuid', strtolower((string) $filters['form']))->value('id')) !== null) {
            $q->where('form_id', $formId);
        }
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage(max(1, (int) ($filters['page'] ?? 1)), 25)->get()->all();

        return ['rows' => array_map(fn (object $r) => $this->present($r, false), $rows), 'total' => $total];
    }

    public function find(string $uuid): ?object
    {
        return DB::table('submission_journal')->where('uuid', strtolower($uuid))->first();
    }

    /** @return array<string, mixed> */
    public function present(object $r, bool $withPayload): array
    {
        $form = Form::query()->find($r->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $user = $r->user_id === null ? null : DB::table('users')->where('id', $r->user_id)->first(['name', 'email']);
        $record = $rt === null || $r->record_id === null ? null : DB::table($rt->table)->where('id', $r->record_id)->value('uuid');
        $out = [
            'uuid' => strtolower((string) $r->uuid), 'status' => $r->status === 'failed' && $r->error_trace === null ? 'rejected' : $r->status,
            'operation' => $r->operation, 'source' => $r->source, 'attempts' => (int) $r->attempts,
            'form' => $form === null ? null : ['uuid' => strtolower((string) $form->uuid), 'name' => $this->formName($form)],
            'version' => (int) (DB::table('form_versions')->where('id', $r->form_version_id)->value('version_number') ?? 0) ?: null,
            'record' => $record === null ? null : strtolower((string) $record),
            'user' => $user === null ? null : ['name' => $user->name, 'email' => $user->email],
            'error' => $r->error_message, 'correlation_id' => $r->correlation_id,
            'created_at' => self::iso($r->created_at), 'updated_at' => self::iso($r->updated_at),
            'edited' => $r->edited_payload !== null, 'discard_reason' => $r->discard_reason,
            'retryable' => $r->status === 'failed' && in_array($r->operation, ['create', 'update', 'transition', 'delete', 'restore'], true),
        ];
        if ($withPayload) {
            $out['payload'] = $rt === null ? null : $this->masked($rt, $this->payloadOf($r));
            $out['trace'] = $r->error_trace;
        }

        return $out;
    }

    /**
     * Keeps an edited payload for the next retry; the original stays. A value
     * still masked keeps what was submitted.
     *
     * @param  array<string, mixed>  $values
     */
    public function edit(object $r, array $values, User $operator): void
    {
        if ($r->status !== 'failed' || ! in_array($r->operation, ['create', 'update', 'transition'], true)) {
            throw ValidationException::withMessages(['payload' => __('operations.not_editable')]);
        }
        $current = $this->payloadOf($r);
        $form = Form::query()->find($r->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form) ?? throw ValidationException::withMessages(['payload' => __('operations.form_unavailable')]);
        $next = [];
        foreach ($values as $key => $value) {
            if ($r->operation === 'transition' && $key === 'transition') {
                $next[$key] = $current[$key] ?? null;

                continue;
            }
            if ($rt !== null && ! isset($rt->keys[$key])) {
                throw ValidationException::withMessages(['payload' => __('operations.unknown_field', ['field' => $key])]);
            }
            $next[$key] = $value === self::MASK ? ($current[$key] ?? null) : $value;
        }
        DB::table('submission_journal')->where('id', $r->id)->update([
            'edited_payload' => json_encode(['sealed' => Crypt::encryptString((string) json_encode($next, JSON_UNESCAPED_UNICODE))], JSON_THROW_ON_ERROR),
            'edited_by' => $operator->id, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        $this->audit->record('operations.submission_edited', 'operations', null, 'submission_journal', (int) $r->id, ['fields' => array_keys($next)], $operator->id, null, (int) $r->form_id);
    }

    /**
     * Retries a failed submission as the person who made it.
     *
     * @return array{uuid: string, ok: bool, error: string|null}
     */
    public function retry(object $r, User $operator): array
    {
        $uuid = strtolower((string) $r->uuid);
        try {
            if ($r->status !== 'failed' || ! in_array($r->operation, ['create', 'update', 'transition', 'delete', 'restore'], true)) {
                throw new RecordException(422, 'not_retryable', __('operations.not_retryable'));
            }
            $user = $r->user_id === null ? null : User::query()->find($r->user_id);
            if ($user === null || $user->status !== 'active') {
                throw new RecordException(422, 'user_inactive', __('operations.submitter_inactive'));
            }
            $form = Form::query()->find($r->form_id);
            $rt = $form === null ? null : $this->runtimes->forForm($form);
            if ($rt === null) {
                throw new RecordException(422, 'form_unavailable', __('operations.form_unavailable'));
            }
            // The person already answered any duplicate warning when they submitted; a block rule still applies.
            app(DuplicateGuard::class)->acknowledge(true);
            $this->replay($rt, $user, $r, $r->edited_payload !== null ? self::unseal((string) $r->edited_payload) : $this->payloadOf($r));
            $this->audit->record('operations.submission_retried', 'operations', null, 'submission_journal', (int) $r->id, ['ok' => true], $operator->id, $user->id, (int) $r->form_id);

            return ['uuid' => $uuid, 'ok' => true, 'error' => null];
        } catch (RecordException|ValidationException $e) {
            $message = $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
            $this->audit->record('operations.submission_retried', 'operations', null, 'submission_journal', (int) $r->id, ['ok' => false], $operator->id, null, (int) $r->form_id);

            return ['uuid' => $uuid, 'ok' => false, 'error' => $message];
        } catch (Throwable $e) {
            report($e);
            $this->audit->record('operations.submission_retried', 'operations', null, 'submission_journal', (int) $r->id, ['ok' => false], $operator->id, null, (int) $r->form_id);

            return ['uuid' => $uuid, 'ok' => false, 'error' => __('operations.retry_failed')];
        }
    }

    public function discard(object $r, string $reason, User $operator): void
    {
        $n = DB::table('submission_journal')->where('id', $r->id)->where('status', 'failed')->update([
            'status' => 'discarded', 'discard_reason' => mb_substr($reason, 0, 2000), 'discarded_by' => $operator->id, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        if ($n === 0) {
            throw ValidationException::withMessages(['submission' => __('operations.not_discardable')]);
        }
        $this->audit->record('operations.submission_discarded', 'operations', null, 'submission_journal', (int) $r->id, ['reason' => mb_substr($reason, 0, 500)], $operator->id, null, (int) $r->form_id);
    }

    /** @param  array<string, mixed>  $payload */
    private function replay(FormRuntime $rt, User $user, object $r, array $payload): void
    {
        $key = (string) $r->idempotency_key;
        $record = $r->record_id === null ? null : DB::table($rt->table)->where('id', $r->record_id)->value('uuid');
        $recordUuid = $record === null ? null : strtolower((string) $record);
        $version = $r->expected_row_version === null ? null : (int) $r->expected_row_version;
        if ($r->operation !== 'create' && $recordUuid === null) {
            throw new RecordException(404, 'not_found', __('records.not_found'));
        }
        match ($r->operation) {
            'create' => $this->pipeline->create($rt, $user, $payload, [], $key, (string) $r->source),
            'update' => $this->pipeline->update($rt, $user, (string) $recordUuid, (int) $version, $payload, $key, (string) $r->source),
            'transition' => $this->pipeline->transition($rt, $user, (string) $recordUuid, (string) ($payload['transition'] ?? ''), (int) $version, array_diff_key($payload, ['transition' => true]), $key, (string) $r->source),
            'delete' => $this->pipeline->delete($rt, $user, (string) $recordUuid, (int) $version, $key),
            'restore' => $this->pipeline->restore($rt, $user, (string) $recordUuid, $key),
            default => throw new RecordException(422, 'not_retryable', __('operations.not_retryable')),
        };
    }

    /** @return array<string, mixed> */
    private function payloadOf(object $r): array
    {
        return self::unseal((string) $r->payload);
    }

    /** @return array<string, mixed> */
    private static function unseal(string $stored): array
    {
        $sealed = json_decode($stored, true)['sealed'] ?? null;
        if (! is_string($sealed)) {
            return [];
        }
        try {
            return (array) json_decode(Crypt::decryptString($sealed), true);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function masked(FormRuntime $rt, array $payload): array
    {
        foreach ($payload as $key => $value) {
            $f = isset($rt->keys[$key]) ? ($rt->fields[$rt->keys[$key]] ?? null) : null;
            if ($f !== null && (($f['flags']['sensitive'] ?? false) || ($f['flags']['encrypted'] ?? false)) && $value !== null) {
                $payload[$key] = self::MASK;
            }
        }

        return $payload;
    }

    private function formName(Form $form): string
    {
        return $this->translator->many('form', [$form->id], ['name'])[$form->id]['name'] ?? Translator::humanize((string) $form->key);
    }

    private static function iso(mixed $at): ?string
    {
        return $at === null ? null : Carbon::parse((string) $at, 'UTC')->toIso8601ZuluString();
    }
}
