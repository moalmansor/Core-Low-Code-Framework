<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Http\Controllers;

use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Models\Form;
use App\Modules\Workflow\StatusMappings;
use App\Modules\Workflow\WorkflowDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The workflow designer's API (specification §4.12, architecture §21
 * Workflow and SLA): the whole workflow document with an optimistic
 * concurrency hash, its SLA rules alone, and the status mapping screen.
 */
final class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowDocument $workflow,
        private readonly StatusMappings $mappings,
        private readonly PublishedDefinitions $definitions,
    ) {}

    public function show(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => $this->state($form)]);
    }

    public function update(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'document' => ['required', 'array'],
            'document.statuses' => ['present', 'array'],
            'document.transitions' => ['present', 'array'],
            'document.sla' => ['present', 'array'],
            'base_hash' => ['required', 'string', 'size:64'],
        ]);
        $this->guardHash($form, $data['base_hash']);
        $this->workflow->save($form, $this->document($request->input('document')), (int) Auth::id());

        return response()->json(['data' => $this->state($form->refresh())]);
    }

    public function sla(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $state = $this->state($form);

        return response()->json(['data' => ['sla' => $state['document']['sla'], 'statuses' => $state['document']['statuses'], 'transitions' => $state['document']['transitions'], 'hash' => $state['hash']]]);
    }

    public function updateSla(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['sla' => ['present', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        $this->guardHash($form, $data['base_hash']);
        $doc = $this->workflow->load($form);
        $doc['sla'] = $this->document(['statuses' => [], 'transitions' => [], 'sla' => $request->input('sla')])['sla'];
        $this->workflow->save($form, $this->document($doc), (int) Auth::id());

        return $this->sla($form->refresh());
    }

    public function mapping(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => $this->mappings->overview($form, $this->published($form), $this->workflow->load($form))]);
    }

    public function chooseMapping(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'mappings' => ['present', 'array', 'max:100'],
            'mappings.*.from' => ['required', 'uuid'],
            'mappings.*.to' => ['required', 'uuid'],
        ]);
        $this->mappings->choose($form, array_map(static fn (array $m) => ['from' => strtolower($m['from']), 'to' => strtolower($m['to'])], $data['mappings']), $this->published($form), $this->workflow->load($form), (int) Auth::id());

        return $this->mapping($form);
    }

    /** @return array<string, mixed> */
    private function state(Form $form): array
    {
        $doc = $this->workflow->load($form);
        $check = $this->workflow->problems(json_decode((string) json_encode($doc), true));

        return [
            'document' => $doc,
            'hash' => $this->workflow->hash($form),
            'problems' => $check['problems'],
            'warnings' => $check['warnings'],
            'published' => $form->current_version_id !== null,
        ];
    }

    private function guardHash(Form $form, string $hash): void
    {
        if (! hash_equals($this->workflow->hash($form), $hash)) {
            abort(response()->json(['message' => __('workflow.changed_elsewhere'), 'code' => 'workflow_changed', 'data' => $this->state($form)], 409));
        }
    }

    /**
     * The submitted document with uuids lower-cased and i18n maps as arrays.
     *
     * @return array{statuses: list<array<string, mixed>>, transitions: list<array<string, mixed>>, sla: list<array<string, mixed>>}
     */
    private function document(mixed $doc): array
    {
        $doc = json_decode((string) json_encode($doc), true);
        $lower = static fn (mixed $v) => is_string($v) ? strtolower($v) : $v;
        foreach (['statuses', 'transitions', 'sla'] as $k) {
            $doc[$k] = array_values(is_array($doc[$k] ?? null) ? $doc[$k] : []);
        }
        foreach ($doc['statuses'] as &$s) {
            if (is_array($s)) {
                $s['uuid'] = $lower($s['uuid'] ?? null);
            }
        }
        unset($s);
        foreach ($doc['transitions'] as &$t) {
            if (is_array($t)) {
                foreach (['uuid', 'from', 'to'] as $k) {
                    $t[$k] = $lower($t[$k] ?? null);
                }
                $t['requiredFields'] = array_map($lower, (array) ($t['requiredFields'] ?? []));
                if (isset($t['approval']['rejectionStatus'])) {
                    $t['approval']['rejectionStatus'] = $lower($t['approval']['rejectionStatus']);
                }
            }
        }
        unset($t);
        foreach ($doc['sla'] as &$r) {
            if (is_array($r)) {
                foreach (['uuid', 'status', 'calendar'] as $k) {
                    $r[$k] = $lower($r[$k] ?? null);
                }
            }
        }
        unset($r);

        return $doc;
    }

    /** @return array<string, mixed>|null */
    private function published(Form $form): ?array
    {
        return $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
    }
}
