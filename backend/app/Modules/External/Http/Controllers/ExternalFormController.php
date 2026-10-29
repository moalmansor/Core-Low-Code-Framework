<?php

declare(strict_types=1);

namespace App\Modules\External\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\External\ExternalForms;
use App\Modules\External\Models\ExternalForm;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** External forms (specification §4.32, ADR-0058); everything needs "Manage external access". */
final class ExternalFormController extends Controller
{
    public function __construct(private readonly ExternalForms $forms, private readonly AuditWriter $audit, private readonly FormRuntimes $runtimes) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_external_access');

        return response()->json(['data' => ExternalForm::query()->orderBy('id')->get()->map(fn (ExternalForm $x) => $this->forms->summary($x))->values()->all()]);
    }

    /** What an external form of this form can show and start in. */
    public function fields(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $uuid = (string) $request->query('form', '');
        $form = Str::isUuid($uuid) ? Form::query()->where('uuid', strtolower($uuid))->first() : null;
        $rt = $form === null || ! in_array($form->state, ['published', 'schema_inconsistent'], true) ? null : $this->runtimes->forForm($form);
        abort_if($rt === null, 404, __('external.form_unpublished'));
        $wf = WorkflowRuntime::for($rt);
        $statuses = [];
        foreach (DB::table('statuses')->where('form_id', $form->id)->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get(['id', 'uuid']) as $s) {
            $status = $wf->status((int) $s->id);
            if ($status !== null) {
                $statuses[] = ['uuid' => strtolower((string) $s->uuid), 'label' => WorkflowRuntime::label($status), 'initial' => (int) $s->id === $wf->initialId()];
            }
        }

        return response()->json(['data' => ['fields' => $this->forms->exposable($rt), 'statuses' => $statuses]]);
    }

    public function show(string $form): JsonResponse
    {
        Gate::authorize('system.manage_external_access');

        return response()->json(['data' => $this->forms->present($this->find($form))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $data = $this->forms->normalize((array) $request->input('form', []), null);
        $x = new ExternalForm;
        DB::transaction(function () use ($x, $data): void {
            $x->forceFill($data['row'] + ['submissions_count' => 0, 'password_hash' => is_string($data['password']) ? Hash::make($data['password']) : null])->save();
            foreach ($data['i18n'] as $field => $values) {
                $x->setTranslations($field, $values);
            }
        });
        $this->audit->record('external.form_created', 'config', null, 'external_form', $x->id, ['slug' => $x->slug], null, null, $x->form_id);

        return response()->json(['data' => $this->forms->present($x->refresh())], 201);
    }

    public function update(Request $request, string $form): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $x = $this->find($form);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->forms->present($x)['hash'], (string) $request->input('base_hash')), 409, __('external.changed_meanwhile'));
        $data = $this->forms->normalize((array) $request->input('form', []), $x);
        $before = ['slug' => $x->slug, 'active' => $x->is_active, 'fields' => $x->exposed_fields];
        DB::transaction(function () use ($x, $data): void {
            $row = $data['row'];
            if ($data['password'] === false) {
                $row['password_hash'] = null;
            } elseif (is_string($data['password'])) {
                $row['password_hash'] = Hash::make($data['password']);
            }
            $x->forceFill($row)->save();
            foreach ($data['i18n'] as $field => $values) {
                $x->setTranslations($field, $values);
            }
        });
        $this->audit->record('external.form_updated', 'config', null, 'external_form', $x->id,
            ['before' => $before, 'after' => ['slug' => $x->slug, 'active' => $x->is_active, 'fields' => $x->exposed_fields], 'password_changed' => $data['password'] !== null], null, null, $x->form_id);

        return response()->json(['data' => $this->forms->present($x->refresh())]);
    }

    public function destroy(string $form): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $x = $this->find($form);
        DB::transaction(function () use ($x): void {
            $this->audit->record('external.form_deleted', 'config', null, 'external_form', $x->id, ['slug' => $x->slug], null, null, $x->form_id);
            app(Translator::class)->forget('external_form', $x->id);
            $x->delete();
        });

        return response()->json(null, 204);
    }

    private function find(string $uuid): ExternalForm
    {
        $x = Str::isUuid($uuid) ? ExternalForm::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($x === null, 404);

        return $x;
    }
}
