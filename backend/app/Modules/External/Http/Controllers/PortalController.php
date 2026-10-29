<?php

declare(strict_types=1);

namespace App\Modules\External\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\External\ExternalAccounts;
use App\Modules\External\ExternalForms;
use App\Modules\External\Models\ExternalForm;
use App\Modules\External\Models\ExternalUser;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Workflow\Runtime\WorkflowEngine;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The external portal (specification §4.32, ADR-0058): registration with
 * email verification, sign-in, the forms open to the signed-in external
 * user, and their own records — only the records they submitted, showing
 * only the fields the external form shows, with the status.
 */
final class PortalController extends Controller
{
    public function __construct(
        private readonly ExternalAccounts $accounts,
        private readonly ExternalForms $forms,
        private readonly TenantContext $tenant,
        private readonly Translator $translator,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $me = $this->accounts->current($request);

        return response()->json(['data' => ['registration_open' => $this->accounts->registrationOpen(), 'me' => $me === null ? null : ['name' => $me->name, 'email' => $me->email]]]);
    }

    public function register(Request $request): JsonResponse
    {
        abort_unless($this->accounts->registrationOpen(), 404, __('external.registration_closed'));
        $this->accounts->register($request->only(['name', 'email', 'password', 'password_confirmation']), (string) $request->ip(), app()->getLocale());

        return response()->json(['data' => ['next' => 'verify']], 202);
    }

    public function resend(Request $request): JsonResponse
    {
        $this->accounts->resend((string) $request->input('email', ''), (string) $request->ip(), app()->getLocale());

        return response()->json(['data' => ['sent' => true]], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $status = $this->accounts->verify((string) $request->input('email', ''), $request->input('code'));
        if ($status === null) {
            return response()->json(['message' => __('external.code_wrong'), 'errors' => ['code' => [__('external.code_wrong')]]], 422);
        }

        return response()->json(['data' => ['status' => $status]]);
    }

    public function login(Request $request): JsonResponse
    {
        $user = $this->accounts->login($request, (string) $request->input('email', ''), (string) $request->input('password', ''));

        return response()->json(['data' => ['name' => $user->name, 'email' => $user->email]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->accounts->logout($request);

        return response()->json(null, 204);
    }

    /** The external forms open to the signed-in external user now. */
    public function forms(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $out = [];
        foreach ($this->visibleForms($me) as $x) {
            if ($this->forms->availability($x) === null) {
                $out[] = ['slug' => $x->slug, 'title' => $this->translator->labelOf($x->translationsFor('title'), Translator::humanize($x->slug)), 'intro' => $this->translator->pick($x->translationsFor('intro'))];
            }
        }

        return response()->json(['data' => $out]);
    }

    /** The signed-in external user's own records, newest first. */
    public function records(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $rows = [];
        foreach ($this->byForm($me) as $x) {
            $rt = $this->forms->runtime($x);
            if ($rt === null) {
                continue;
            }
            $wf = WorkflowRuntime::for($rt);
            $title = $this->translator->labelOf($x->translationsFor('title'), Translator::humanize($x->slug));
            foreach (DB::table($rt->table)->where('external_user_id', $me->id)->whereNull('deleted_at')->orderByDesc('id')->limit(200)->get(['uuid', 'record_number', 'status_id', 'created_at']) as $r) {
                $status = $wf->status($r->status_id === null ? null : (int) $r->status_id);
                $rows[] = [
                    'slug' => $x->slug, 'form' => $title, 'uuid' => strtolower((string) $r->uuid), 'reference' => $r->record_number,
                    'status' => $status === null ? null : ['name' => WorkflowRuntime::label($status), 'color' => $status['color']],
                    'submitted_at' => Carbon::parse((string) $r->created_at, 'UTC')->toIso8601ZuluString(),
                ];
            }
        }
        usort($rows, static fn (array $a, array $b) => strcmp($b['submitted_at'], $a['submitted_at']));

        return response()->json(['data' => $rows]);
    }

    /** One own record, read-only, with the fields its external form shows. */
    public function record(Request $request, string $slug, string $record): JsonResponse
    {
        $me = $this->me($request);
        $x = collect($this->byForm($me))->first(fn (ExternalForm $f) => $f->slug === strtolower($slug));
        $rt = $x === null ? null : $this->forms->runtime($x);
        $id = $rt === null || ! Str::isUuid($record) ? null : DB::table($rt->table)->where('uuid', strtolower($record))->where('external_user_id', $me->id)->whereNull('deleted_at')->value('id');
        abort_if($x === null || $rt === null || $id === null, 404, __('records.not_found'));
        $found = app(RecordStore::class)->find($rt, strtolower($record));
        abort_if($found === null, 404, __('records.not_found'));
        $levels = ['form' => 'read_only', 'groups' => [], 'fields' => [], 'modes' => ['view' => true]];
        foreach ($rt->definition['fields'] as $f) {
            $levels['fields'][$f['uuid']] = in_array($f['uuid'], $x->exposed_fields, true) ? 'read_only' : 'hidden';
        }
        $definition = $this->forms->definition($x, $rt, $this->forms->runAs($x) ?? abort(404));
        $presented = app(RecordPresenter::class)->present($rt, $found, $levels['fields']);
        $wf = WorkflowRuntime::for($rt);
        $status = $found['system']['status_id'] ?? null;

        return response()->json(['data' => [
            'form' => $this->translator->labelOf($x->translationsFor('title'), Translator::humanize($x->slug)),
            'reference' => $found['system']['record_number'] ?? null,
            'status' => app(WorkflowEngine::class)->statusPayload($wf, $status === null ? null : (int) $status),
            'definition' => ['mode' => 'view'] + $definition, 'values' => $presented['values'], 'references' => $presented['references'],
        ]]);
    }

    private function me(Request $request): ExternalUser
    {
        $me = $this->accounts->current($request);
        abort_if($me === null, 401, __('external.sign_in_required'));
        $this->tenant->set($me->organization_id);

        return $me;
    }

    /** @return list<ExternalForm> */
    private function visibleForms(ExternalUser $me): array
    {
        return ExternalForm::query()->where('is_active', true)->orderBy('id')->get()->filter(fn (ExternalForm $x) => $this->forms->admits($x, $me))->values()->all();
    }

    /**
     * One external form per form the user may use (the first one), which
     * decides the fields their records show.
     *
     * @return list<ExternalForm>
     */
    private function byForm(ExternalUser $me): array
    {
        $out = [];
        foreach (ExternalForm::query()->orderBy('id')->get() as $x) {
            if (! isset($out[$x->form_id]) && $this->forms->admits($x, $me)) {
                $out[$x->form_id] = $x;
            }
        }

        return array_values($out);
    }
}
