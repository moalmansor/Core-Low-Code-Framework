<?php

declare(strict_types=1);

namespace App\Modules\Operations\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\AlertRules;
use App\Modules\Operations\EmailMonitor;
use App\Modules\Operations\FailedJobs;
use App\Modules\Operations\FailedSubmissions;
use App\Modules\Operations\Health;
use App\Modules\Records\Templating\HtmlPreviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The Operations Center (specification §4.2, ADR-0045): health, the e-mail
 * queue, failed submissions, failed jobs and threshold alerts. Everything
 * needs "Manage operations"; every action is audited by the service doing it.
 */
final class OperationsController extends Controller
{
    public function __construct(
        private readonly Health $health,
        private readonly EmailMonitor $emails,
        private readonly FailedSubmissions $submissions,
        private readonly FailedJobs $jobs,
        private readonly AlertRules $alerts,
    ) {}

    public function health(): JsonResponse
    {
        Gate::authorize('system.manage_operations');

        return response()->json(['data' => $this->health->snapshot()]);
    }

    // ------------------------------------------------------------------ e-mails

    public function emails(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $f = $request->validate(['status' => ['sometimes', 'nullable', Rule::in(EmailMonitor::STATUSES)], 'search' => ['sometimes', 'nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $out = $this->emails->list($f);

        return response()->json(['data' => $out['rows'], 'meta' => ['total' => $out['total']]]);
    }

    /** A short-lived link to the rendered e-mail, shown in a sandboxed frame (it is shown, not trusted). */
    public function emailHtml(string $email, HtmlPreviews $previews): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $row = Str::isUuid($email) ? $this->emails->find($email) : null;
        abort_if($row === null, 404);

        return response()->json(['data' => ['url' => $previews->put((string) $row->body_html)]]);
    }

    public function resendEmails(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $data = $request->validate([
            'emails' => ['required', 'array', 'min:1', 'max:200'], 'emails.*' => ['uuid'],
            'recipients' => ['sometimes', 'nullable', 'array'], 'recipients.to' => ['required_with:recipients', 'array', 'min:1', 'max:50'], 'recipients.to.*' => ['email:rfc', 'max:254'],
            'recipients.cc' => ['sometimes', 'array', 'max:50'], 'recipients.cc.*' => ['email:rfc', 'max:254'], 'recipients.bcc' => ['sometimes', 'array', 'max:50'], 'recipients.bcc.*' => ['email:rfc', 'max:254'],
        ]);
        $rows = $this->rows('email_queue', $data['emails']);
        abort_if(isset($data['recipients']) && count($rows) !== 1, 422, __('operations.recipients_single'));

        return response()->json(['data' => ['resent' => $this->emails->resend($rows, $data['recipients'] ?? null, $this->user()->id)]]);
    }

    public function cancelEmails(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $data = $request->validate(['emails' => ['required', 'array', 'min:1', 'max:200'], 'emails.*' => ['uuid']]);

        return response()->json(['data' => ['cancelled' => $this->emails->cancel($this->rows('email_queue', $data['emails']), $this->user()->id)]]);
    }

    // -------------------------------------------------------------- submissions

    public function submissions(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $f = $request->validate(['status' => ['sometimes', 'nullable', Rule::in(['failed', 'rejected', 'discarded'])], 'form' => ['sometimes', 'nullable', 'uuid'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $out = $this->submissions->list($f);

        return response()->json(['data' => $out['rows'], 'meta' => ['total' => $out['total']]]);
    }

    public function submission(string $submission): JsonResponse
    {
        Gate::authorize('system.manage_operations');

        return response()->json(['data' => $this->submissions->present($this->submissionRow($submission), true)]);
    }

    public function editSubmission(Request $request, string $submission): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $data = $request->validate(['payload' => ['present', 'array']]);
        $row = $this->submissionRow($submission);
        $this->submissions->edit($row, (array) $data['payload'], $this->user());

        return $this->submission($submission);
    }

    public function retrySubmissions(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $data = $request->validate(['submissions' => ['required', 'array', 'min:1', 'max:100'], 'submissions.*' => ['uuid']]);
        $results = array_map(fn (object $r) => $this->submissions->retry($r, $this->user()), $this->rows('submission_journal', $data['submissions']));

        return response()->json(['data' => ['results' => $results, 'succeeded' => count(array_filter($results, static fn ($r) => $r['ok']))]]);
    }

    public function discardSubmission(Request $request, string $submission): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $this->submissions->discard($this->submissionRow($submission), (string) $data['reason'], $this->user());

        return $this->submission($submission);
    }

    // --------------------------------------------------------------------- jobs

    public function jobs(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $f = $request->validate(['kind' => ['sometimes', 'nullable', Rule::in(FailedJobs::KINDS)], 'page' => ['sometimes', 'integer', 'min:1']]);
        $out = $this->jobs->list($f);

        return response()->json(['data' => $out['rows'], 'meta' => ['total' => $out['total']]]);
    }

    public function job(string $kind, int $id): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        abort_unless(in_array($kind, FailedJobs::KINDS, true), 404);
        $row = $this->jobs->inspect($kind, $id);
        abort_if($row === null, 404);

        return response()->json(['data' => $row]);
    }

    public function retryJob(string $kind, int $id): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        abort_unless(in_array($kind, FailedJobs::KINDS, true), 404);
        $this->jobs->retry($kind, $id, $this->user());

        return response()->json(['data' => ['retried' => true]], 202);
    }

    public function dismissJob(Request $request, string $kind, int $id): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        abort_unless(in_array($kind, FailedJobs::KINDS, true), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $this->jobs->dismiss($kind, $id, (string) $data['reason'], $this->user());

        return response()->json(['data' => ['dismissed' => true]]);
    }

    // ------------------------------------------------------------------- alerts

    public function alertRules(): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $rules = $this->alerts->load();

        return response()->json(['data' => [
            'rules' => $rules, 'hash' => $this->alerts->hash(), 'metrics' => AlertRules::METRICS, 'instant' => AlertRules::INSTANT,
            'values' => (object) collect($rules)->mapWithKeys(fn ($r) => [$r['uuid'] => $this->alerts->measure($r['metric'], $r['window'])])->all(),
        ]]);
    }

    public function saveAlertRules(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_operations');
        $data = $request->validate(['rules' => ['present', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->alerts->hash(), (string) $data['base_hash'])) {
            return response()->json(['message' => __('operations.changed_elsewhere'), 'code' => 'rules_changed'], 409);
        }
        $this->alerts->save(array_values((array) $data['rules']), $this->user()->id);

        return $this->alertRules();
    }

    /**
     * @param  list<string>  $uuids
     * @return list<object>
     */
    private function rows(string $table, array $uuids): array
    {
        return DB::table($table)->whereIn('uuid', array_map('strtolower', $uuids))->orderBy('id')->get()->all();
    }

    private function submissionRow(string $uuid): object
    {
        $row = Str::isUuid($uuid) ? $this->submissions->find($uuid) : null;
        abort_if($row === null, 404);

        return $row;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
