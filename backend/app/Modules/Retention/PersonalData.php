<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\Models\Justification;
use App\Modules\Records\FileStore;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\ValueCodec;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Personal data requests (specification §4.26, architecture §19.16).
 *
 * A request names a person by e-mail, an identifier (a national id, a
 * customer number), a name or a user account. Locating searches every
 * published form's fields marked as personal data (encrypted ones through
 * their blind index), user accounts, and the logs that refer to the person,
 * and lists what it found for review. Nothing is changed until an
 * administrator approves the request with a justification:
 *
 * - export: a JSON bundle of everything found is kept as the request's file;
 * - anonymize: personal-data values of the found records are replaced with
 *   irreversible tokens, the person's account is anonymized, e-mail log
 *   addresses are replaced, and each record's subject key is destroyed, so
 *   the values sealed in the audit trail become unreadable while every entry
 *   and hash stays in place (ADR-0014);
 * - delete: the found records are removed for good (with their keys), the
 *   person's e-mails and notifications are deleted, and the account is
 *   anonymized (audit entries keep pointing at it, so it is not removed).
 *
 * Records under legal hold are never changed; they are reported as skipped.
 */
final class PersonalData
{
    public const TYPES = ['locate', 'export', 'delete', 'anonymize'];

    /** Most records listed per form in the findings. */
    private const MAX_RECORDS = 10000;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordRemoval $removal,
        private readonly SubjectKeys $keys,
        private readonly LegalHolds $holds,
        private readonly DatabaseDriver $driver,
        private readonly AuditWriter $audit,
        private readonly FileStore $files,
    ) {}

    /**
     * Normalized identifiers of a subject, and their blind index.
     *
     * @param  array<string, mixed>  $subject
     * @return array{subject: array<string, string>, hash: string}
     */
    public static function normalize(array $subject): array
    {
        $clean = [];
        foreach (['email', 'identifier', 'name', 'user'] as $k) {
            $v = trim((string) ($subject[$k] ?? ''));
            if ($v !== '') {
                $clean[$k] = $k === 'email' || $k === 'user' ? mb_strtolower($v) : $v;
            }
        }
        ksort($clean);

        return ['subject' => $clean, 'hash' => hash_hmac('sha256', (string) json_encode($clean, JSON_UNESCAPED_UNICODE), 'pdr|'.config('app.key'))];
    }

    /** @return array<string, string> */
    public function subjectOf(object $request): array
    {
        return json_decode(Crypt::decryptString((string) $request->subject), true) ?: [];
    }

    /** Searches for the request's subject and stores the findings; a locate request is then complete. */
    public function locate(int $requestId): void
    {
        $request = DB::table('personal_data_requests')->where('id', $requestId)->first();
        if ($request === null || ! in_array($request->status, ['received', 'locating'], true)) {
            return;
        }
        $this->status($requestId, 'locating');
        try {
            $findings = $this->find($this->subjectOf($request));
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('personal_data_requests')->where('id', $requestId)->update([
                'findings' => json_encode($findings, JSON_UNESCAPED_UNICODE), 'updated_at' => $now,
                'status' => $request->type === 'locate' ? 'completed' : 'awaiting_review',
                'completed_at' => $request->type === 'locate' ? $now : null,
            ]);
            $this->audit->record('personal_data.located', 'security', null, 'personal_data_request', $requestId, ['type' => $request->type, 'summary' => $this->summary($findings)]);
        } catch (Throwable $e) {
            report($e);
            $this->status($requestId, 'failed');
        }
    }

    /**
     * Approves and carries out an export, delete or anonymize request.
     * Returns the request's new status.
     */
    public function execute(object $request, User $by, string $reason): string
    {
        if ($request->status !== 'awaiting_review' || $request->type === 'locate') {
            throw new RuntimeException(__('retention.pd.not_awaiting'));
        }
        $justification = $this->justify($request, $by, $reason);
        DB::table('personal_data_requests')->where('id', $request->id)->update(['status' => 'executing', 'justification_id' => $justification, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'updated_by' => $by->id]);
        // Findings are taken again now, so the request acts on what exists at approval, not at review.
        $subject = $this->subjectOf($request);
        $findings = $this->find($subject);
        $result = ['skipped_legal_hold' => 0, 'records' => 0];
        $fileId = null;
        try {
            match ($request->type) {
                'export' => $fileId = $this->export($request, $subject, $findings),
                'anonymize' => $result = $this->anonymize($request, $subject, $findings),
                'delete' => $result = $this->delete($request, $subject, $findings),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
            $this->status((int) $request->id, 'failed');
            $this->audit->record('personal_data.failed', 'security', null, 'personal_data_request', (int) $request->id, ['type' => $request->type], $by->id, null, null, null, null, $justification);

            return 'failed';
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $findings['result'] = $result;
        DB::table('personal_data_requests')->where('id', $request->id)->update([
            'status' => 'completed', 'completed_at' => $now, 'completed_by' => $by->id, 'result_file_id' => $fileId, 'updated_at' => $now,
            'findings' => json_encode($findings, JSON_UNESCAPED_UNICODE),
        ]);
        $this->audit->record('personal_data.'.($request->type === 'export' ? 'exported' : ($request->type === 'delete' ? 'deleted' : 'anonymized')), 'security', null, 'personal_data_request', (int) $request->id,
            ['type' => $request->type, 'summary' => $this->summary($findings), 'result' => $result], $by->id, null, null, null, null, $justification);

        return 'completed';
    }

    public function reject(object $request, User $by, string $reason): void
    {
        if (! in_array($request->status, ['received', 'awaiting_review'], true)) {
            throw new RuntimeException(__('retention.pd.not_awaiting'));
        }
        $justification = $this->justify($request, $by, $reason);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('personal_data_requests')->where('id', $request->id)->update(['status' => 'rejected', 'justification_id' => $justification, 'completed_at' => $now, 'completed_by' => $by->id, 'updated_at' => $now]);
        $this->audit->record('personal_data.rejected', 'security', null, 'personal_data_request', (int) $request->id, ['type' => $request->type], $by->id, null, null, null, null, $justification);
    }

    /**
     * Everything that relates to the subject.
     *
     * @param  array<string, string>  $subject
     * @return array<string, mixed>
     */
    public function find(array $subject): array
    {
        $user = null;
        if (isset($subject['user']) && Str::isUuid($subject['user'])) {
            $user = User::query()->where('uuid', $subject['user'])->first();
        }
        if ($user === null && isset($subject['email'])) {
            $user = User::query()->whereRaw('lower(email) = ?', [$subject['email']])->first();
        }
        $terms = array_values(array_filter([$subject['email'] ?? null, $subject['identifier'] ?? null, $subject['name'] ?? null, $user?->email]));
        $terms = array_values(array_unique(array_map(static fn ($t) => (string) $t, $terms)));
        $forms = [];
        foreach (Form::query()->whereNotNull('current_version_id')->orderBy('id')->get() as $form) {
            $rt = $this->runtimes->forForm($form);
            if ($rt === null) {
                continue;
            }
            $found = $this->matchForm($rt, $terms);
            $owned = $user === null ? [] : DB::table($rt->table)->where(fn ($q) => $q->where('owner_user_id', $user->id)->orWhere('created_by', $user->id))
                ->orderBy('id')->limit(self::MAX_RECORDS)->pluck('id')->map(static fn ($v) => (int) $v)->all();
            if ($found['records'] === [] && $owned === [] && $found['unsearchable'] === []) {
                continue;
            }
            $forms[] = [
                'uuid' => strtolower($form->uuid), 'key' => $form->key, 'name' => $form->translate('name') ?? Translator::humanize($form->key),
                'records' => $found['records'], 'fields' => $found['fields'], 'owned' => array_values(array_diff($owned, $found['records'])),
                'held' => $found['records'] === [] ? 0 : DB::table($rt->table)->whereIn('id', array_slice($found['records'], 0, 2000))->where('legal_hold', true)->count(),
                'unsearchable' => $found['unsearchable'],
            ];
        }
        $emails = array_values(array_filter([$subject['email'] ?? null, $user?->email === null ? null : mb_strtolower((string) $user->email)]));
        $emailRows = $emails === [] ? 0 : DB::table('email_queue')->where(function ($q) use ($emails): void {
            foreach (array_unique($emails) as $e) {
                $this->driver->caseInsensitiveLike($q, 'to', $e, 'contains');
            }
        })->count();

        return [
            'user' => $user === null ? null : ['uuid' => strtolower($user->uuid), 'name' => $user->name, 'email' => $user->email, 'status' => $user->status, 'anonymized' => $user->getAttribute('anonymized_at') !== null],
            'forms' => $forms,
            'logs' => [
                'audit_logs' => $user === null ? 0 : DB::table('audit_logs')->where(fn ($q) => $q->where('actor_user_id', $user->id)->orWhere('subject_user_id', $user->id))->count(),
                'email_queue' => $emailRows,
                'in_app_notifications' => $user === null ? 0 : DB::table('in_app_notifications')->where('user_id', $user->id)->count(),
                'error_logs' => $user === null ? 0 : DB::table('error_logs')->where('user_id', $user->id)->count(),
                'submission_journal' => $user === null ? 0 : DB::table('submission_journal')->where('user_id', $user->id)->count(),
            ],
            'located_at' => Carbon::now('UTC')->toIso8601ZuluString(),
        ];
    }

    /**
     * Records of a form whose personal-data fields hold one of the terms.
     *
     * @param  list<string>  $terms
     * @return array{records: list<int>, fields: list<string>, unsearchable: list<string>}
     */
    private function matchForm(FormRuntime $rt, array $terms): array
    {
        $ids = [];
        $fields = [];
        $unsearchable = [];
        foreach ($rt->mainFields() as $f) {
            if (! ($f['flags']['personal'] ?? false) || ! $rt->isStored($f) || $terms === []) {
                continue;
            }
            $main = $rt->column($f['uuid']);
            if ($main === null) {
                continue;
            }
            $q = DB::table($rt->table);
            if ($main['encrypted'] ?? false) {
                $bidx = $rt->column($f['uuid'], 'bidx');
                if ($bidx === null) {
                    $unsearchable[] = $f['key'];

                    continue;
                }
                $q->whereIn($bidx['name'], array_map(static fn ($t) => ValueCodec::blindIndex($t), $terms));
            } elseif (in_array($main['type'] ?? '', ['string', 'text'], true)) {
                $q->where(function ($w) use ($main, $terms): void {
                    foreach ($terms as $t) {
                        $w->orWhere(fn ($x) => $this->driver->caseInsensitiveLike($x, (string) $main['name'], $t, 'equals'));
                    }
                });
            } else {
                continue;
            }
            $hit = $q->orderBy('id')->limit(self::MAX_RECORDS)->pluck('id')->map(static fn ($v) => (int) $v)->all();
            if ($hit !== []) {
                $fields[] = $f['key'];
                array_push($ids, ...$hit);
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        return ['records' => array_slice($ids, 0, self::MAX_RECORDS), 'fields' => $fields, 'unsearchable' => $unsearchable];
    }

    /**
     * @param  array<string, string>  $subject
     * @param  array<string, mixed>  $findings
     */
    private function export(object $request, array $subject, array $findings): int
    {
        $bundle = ['format' => 'lcf.personal-data-export.v1', 'request' => strtolower((string) $request->uuid), 'subject' => $subject, 'generated_at' => Carbon::now('UTC')->toIso8601ZuluString(),
            'user' => $findings['user'], 'forms' => [], 'emails' => []];
        foreach ($findings['forms'] as $f) {
            $rt = $this->runtimes->forUuid($f['uuid']);
            if ($rt === null) {
                continue;
            }
            $records = [];
            foreach (array_chunk([...$f['records'], ...$f['owned']], 200) as $chunk) {
                foreach ($this->store->hydrate($rt, DB::table($rt->table)->whereIn('id', $chunk)->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all()) as $r) {
                    $records[] = ['uuid' => $r['uuid'], 'values' => $r['values'], 'created_at' => $r['system']['created_at'] ?? null];
                }
            }
            $bundle['forms'][] = ['form' => $f['key'], 'name' => $f['name'], 'records' => $records];
        }
        $email = $findings['user']['email'] ?? ($subject['email'] ?? null);
        if ($email !== null) {
            $q = DB::table('email_queue')->orderBy('id')->limit(5000);
            $this->driver->caseInsensitiveLike($q, 'to', (string) $email, 'contains');
            $bundle['emails'] = $q->get(['subject', 'status', 'created_at', 'sent_at'])->map(static fn ($e) => (array) $e)->all();
        }
        $tmp = (string) tempnam(sys_get_temp_dir(), 'lcf-pd-');
        file_put_contents($tmp, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            return $this->files->storeGenerated($tmp, 'personal-data-'.substr(strtolower((string) $request->uuid), 0, 8), 'json', 'application/json', 'personal_data_request', ['owner_id' => (int) $request->id])->id;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  array<string, string>  $subject
     * @param  array<string, mixed>  $findings
     * @return array{skipped_legal_hold: int, records: int}
     */
    private function anonymize(object $request, array $subject, array $findings): array
    {
        $done = 0;
        $skipped = 0;
        foreach ($findings['forms'] as $f) {
            $rt = $this->runtimes->forUuid($f['uuid']);
            if ($rt === null || $f['records'] === []) {
                continue;
            }
            $this->holds->recompute($rt);
            $personal = array_values(array_filter($rt->mainFields(), static fn ($x) => (bool) ($x['flags']['personal'] ?? false) && $rt->isStored($x)));
            $rowPersonal = [];
            foreach ($rt->repeaters as $repUuid => $rep) {
                foreach ($rt->rowFields($repUuid) as $x) {
                    if (($x['flags']['personal'] ?? false) && $rt->isStored($x)) {
                        $rowPersonal[$rep['table']][] = $x;
                    }
                }
            }
            $pipeline = app(RecordPipeline::class);
            foreach ($f['records'] as $id) {
                if ((bool) DB::table($rt->table)->where('id', $id)->value('legal_hold')) {
                    $skipped++;

                    continue;
                }
                // Optimistic concurrency like every record write: retried on a version that moved meanwhile.
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    $current = $this->store->findById($rt, $id);
                    if ($current === null) {
                        break;
                    }
                    $values = $current['values'];
                    foreach ($personal as $x) {
                        $values[$x['key']] = $this->token($x, $values[$x['key']] ?? null);
                    }
                    $ok = DB::transaction(function () use ($rt, $id, $current, $values, $personal, $rowPersonal, $pipeline, $request): bool {
                        $ok = $this->store->update($rt, $id, $current['row_version'], $values, array_column($personal, 'key'),
                            ['search_text' => $pipeline->searchText($rt, $values)], $rt->form->organization_id, null);
                        if (! $ok) {
                            return false;
                        }
                        foreach ($rowPersonal as $table => $rowFields) {
                            $cols = [];
                            foreach ($rowFields as $x) {
                                foreach ($rt->columns[$x['uuid']] ?? [] as $c) {
                                    $cols[$c['name']] = null;
                                }
                            }
                            DB::table($table)->where('parent_id', $id)->update($cols);
                        }
                        $this->rewriteJournal($rt, $id, array_column($personal, 'key'));
                        $this->keys->destroy($rt->form->organization_id, $rt->form->id, [$id], (int) $request->id);
                        $this->audit->record('personal_data.anonymized', 'data', null, 'record', $id, ['form' => $rt->form->key, 'request' => strtolower((string) $request->uuid), 'fields' => array_column($personal, 'key')],
                            null, null, $rt->form->id, $id);

                        return true;
                    });
                    if ($ok) {
                        $done++;
                        break;
                    }
                }
            }
        }
        $this->anonymizeUser($findings, $subject, (int) $request->id);
        $this->scrubEmails($findings, $subject, false);

        return ['skipped_legal_hold' => $skipped, 'records' => $done];
    }

    /**
     * @param  array<string, string>  $subject
     * @param  array<string, mixed>  $findings
     * @return array{skipped_legal_hold: int, records: int}
     */
    private function delete(object $request, array $subject, array $findings): array
    {
        $done = 0;
        $skipped = 0;
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        foreach ($findings['forms'] as $f) {
            $rt = $this->runtimes->forUuid($f['uuid']);
            if ($rt === null || $f['records'] === []) {
                continue;
            }
            $this->holds->recompute($rt);
            foreach ($f['records'] as $id) {
                if ((bool) DB::table($rt->table)->where('id', $id)->value('legal_hold')) {
                    $skipped++;

                    continue;
                }
                try {
                    DB::transaction(function () use ($rt, $id, $now, $request): void {
                        $this->removal->remove($rt, $id, $now);
                        DB::table('recycle_bin')->where('form_id', $rt->form->id)->where('record_id', $id)->whereNull('purged_at')->update(['purged_at' => $now]);
                        DB::table('submission_journal')->where('form_id', $rt->form->id)->where('record_id', $id)->delete();
                        $this->keys->destroy($rt->form->organization_id, $rt->form->id, [$id], (int) $request->id);
                        $this->audit->record('record.purged', 'data', null, 'record', $id, ['form' => $rt->form->key, 'personal_data_request' => strtolower((string) $request->uuid)], null, null, $rt->form->id, $id);
                    });
                    $done++;
                } catch (Throwable $e) {
                    // Something still points at the record (a link from another record): it stays and is reported.
                    report($e);
                    $skipped++;
                }
            }
        }
        $user = $this->anonymizeUser($findings, $subject, (int) $request->id);
        if ($user !== null) {
            DB::table('in_app_notifications')->where('user_id', $user)->delete();
        }
        $this->scrubEmails($findings, $subject, true);

        return ['skipped_legal_hold' => $skipped, 'records' => $done];
    }

    /** @param array<string, mixed> $field */
    private function token(array $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $token = 'anonymized-'.bin2hex(random_bytes(5));

        return match ($field['type'] ?? null) {
            'email' => $token.'@anonymized.invalid',
            'text', 'textarea', 'rich_text', 'phone', 'url' => $token,
            default => is_string($value) ? $token : null,
        };
    }

    /** Replaces the personal-data values kept in the record's journal payloads. @param list<string> $keys */
    private function rewriteJournal(FormRuntime $rt, int $recordId, array $keys): void
    {
        foreach (DB::table('submission_journal')->where('form_id', $rt->form->id)->where('record_id', $recordId)->get(['id', 'payload', 'edited_payload']) as $j) {
            $update = [];
            foreach (['payload', 'edited_payload'] as $col) {
                if ($j->{$col} === null) {
                    continue;
                }
                $doc = json_decode((string) $j->{$col}, true) ?: [];
                $sealed = isset($doc['sealed']);
                $data = $sealed ? (json_decode(Crypt::decryptString((string) $doc['sealed']), true) ?: []) : $doc;
                $data = $this->scrubKeys($data, array_flip($keys));
                $update[$col] = $sealed ? json_encode(['sealed' => Crypt::encryptString((string) json_encode($data, JSON_UNESCAPED_UNICODE))]) : json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            if ($update !== []) {
                DB::table('submission_journal')->where('id', $j->id)->update($update);
            }
        }
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, int>  $keys
     * @return array<mixed>
     */
    private function scrubKeys(array $data, array $keys): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && isset($keys[$k])) {
                $data[$k] = $v === null ? null : 'anonymized';
            } elseif (is_array($v)) {
                $data[$k] = $this->scrubKeys($v, $keys);
            }
        }

        return $data;
    }

    /**
     * Anonymizes the person's account: it stays (audit entries refer to it)
     * but holds nothing that identifies them and can no longer sign in.
     *
     * @param  array<string, mixed>  $findings
     * @param  array<string, string>  $subject
     */
    private function anonymizeUser(array $findings, array $subject, int $requestId): ?int
    {
        $uuid = $findings['user']['uuid'] ?? null;
        $user = $uuid === null ? null : User::query()->where('uuid', $uuid)->first();
        if ($user === null) {
            return null;
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('users')->where('id', $user->id)->update([
            'name' => __('retention.pd.anonymized_user'), 'email' => 'anonymized-'.$user->id.'@anonymized.invalid',
            'username' => 'anonymized-'.$user->id, 'phone' => null, 'job_title' => null, 'attributes' => null, 'external_subject' => null,
            'password' => null, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null,
            'last_login_ip' => null, 'status' => 'disabled', 'anonymized_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('api_tokens')->where('tokenable_id', $user->id)->where('tokenable_type', $user->getMorphClass())->whereNull('revoked_at')->update(['revoked_at' => $now, 'updated_at' => $now]);
        $this->audit->record('user.anonymized', 'security', null, 'user', $user->id, ['request' => $requestId], null, $user->id);

        return $user->id;
    }

    /**
     * Replaces (or, for deletion, removes) the person's address in the
     * e-mail log; the message bodies that named them go with it.
     *
     * @param  array<string, mixed>  $findings
     * @param  array<string, string>  $subject
     */
    private function scrubEmails(array $findings, array $subject, bool $delete): void
    {
        $emails = array_values(array_unique(array_filter([$subject['email'] ?? null, isset($findings['user']['email']) ? mb_strtolower((string) $findings['user']['email']) : null])));
        foreach ($emails as $email) {
            $q = DB::table('email_queue')->whereNotIn('status', ['pending', 'sending']);
            $this->driver->caseInsensitiveLike($q, 'to', $email, 'contains');
            foreach ($q->orderBy('id')->get(['id', 'to', 'cc', 'bcc']) as $row) {
                if ($delete) {
                    DB::table('email_queue')->where('resent_from_id', $row->id)->update(['resent_from_id' => null]);
                    DB::table('email_queue')->where('id', $row->id)->delete();

                    continue;
                }
                $swap = static fn (?string $v) => $v === null ? null : str_ireplace($email, 'anonymized@anonymized.invalid', $v);
                DB::table('email_queue')->where('id', $row->id)->update(['to' => $swap((string) $row->to), 'cc' => $swap($row->cc), 'bcc' => $swap($row->bcc), 'body_html' => '', 'subject' => '—']);
            }
        }
    }

    /** The reason, kept as an immutable justification of context `personal_data`. */
    private function justify(object $request, User $by, string $reason): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $content = ['request' => strtolower((string) $request->uuid), 'context' => 'personal_data', 'type' => $request->type, 'reason_text' => $reason, 'user' => $by->id, 'at' => $now];

        return Justification::query()->create([
            'uuid' => (string) Str::uuid7(), 'organization_id' => (int) $request->organization_id, 'form_id' => null, 'record_id' => null,
            'context' => 'personal_data', 'rule_ids' => [], 'reason_text' => $reason, 'note' => null, 'changed_fields' => [], 'affected_count' => 1,
            'user_id' => $by->id, 'locale' => app()->getLocale(),
            'content_hash' => hash('sha256', (string) json_encode(Canonical::of($content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'created_at' => $now,
        ])->id;
    }

    /** @param array<string, mixed> $findings @return array<string, int> */
    private function summary(array $findings): array
    {
        return [
            'forms' => count($findings['forms'] ?? []),
            'records' => array_sum(array_map(static fn ($f) => count($f['records']), $findings['forms'] ?? [])),
            'user' => ($findings['user'] ?? null) === null ? 0 : 1,
        ];
    }

    private function status(int $id, string $status): void
    {
        DB::table('personal_data_requests')->where('id', $id)->update(['status' => $status, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }
}
