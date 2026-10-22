<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Downloads\DownloadPaths;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\ReportDefinitions;
use App\Modules\Reports\ReportEngine;
use App\Modules\Reports\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reports (specification §4.18, ADR-0053). Building them needs "Manage
 * reports and dashboards"; viewing one needs its own `report.{uuid}.view`
 * and sight of its form, and shows only the viewer's own records.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportDefinitions $definitions,
        private readonly ReportEngine $engine,
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $forms = Form::query()->get()->keyBy('id');

        return response()->json(['data' => Report::query()->orderBy('key')->get()->map(fn (Report $r) => [
            'uuid' => strtolower($r->uuid), 'key' => $r->key, 'label' => $this->translator->labelOf($r->translationsFor('name'), $r->key),
            'visualization' => $r->visualization,
            'form_name' => isset($forms[$r->base_form_id]) ? $this->translator->labelOf($forms[$r->base_form_id]->translationsFor('name'), (string) $forms[$r->base_form_id]->key) : null,
            'updated_at' => Carbon::parse($r->getRawOriginal('updated_at'), 'UTC')->toIso8601ZuluString(),
        ])->values()->all()]);
    }

    /** What the signed-in person may open: reports and dashboards granted to them. */
    public function mine(): JsonResponse
    {
        $user = $this->user();
        $reports = Report::query()->orderBy('key')->get()->filter(fn (Report $r) => $this->access->allows($user, 'report.'.strtolower($r->uuid).'.view'));
        $dashboards = Dashboard::query()->where('is_active', true)->orderBy('key')->get()->filter(fn (Dashboard $d) => $this->access->allows($user, 'dashboard.'.strtolower($d->uuid).'.view'));

        return response()->json(['data' => [
            'reports' => $reports->map(fn (Report $r) => ['uuid' => strtolower($r->uuid), 'label' => $this->translator->labelOf($r->translationsFor('name'), $r->key),
                'description' => $this->translator->pick($r->translationsFor('description')), 'visualization' => $r->visualization])->values()->all(),
            'dashboards' => $dashboards->map(fn (Dashboard $d) => ['uuid' => strtolower($d->uuid), 'label' => $this->translator->labelOf($d->translationsFor('name'), $d->key),
                'description' => $this->translator->pick($d->translationsFor('description'))])->values()->all(),
        ]]);
    }

    /** One level of the fields and links a report on this form can read. */
    public function fields(Request $request, DownloadPaths $paths): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $data = $request->validate(['form' => ['required', 'uuid'], 'prefix' => ['sometimes', 'array', 'max:10'], 'prefix.*' => ['string', 'max:128']]);
        $rt = $this->runtimes->forUuid(strtolower($data['form']));
        abort_if($rt === null, 404);
        $items = $paths->tree($rt, array_values((array) ($data['prefix'] ?? [])), null);
        foreach ($items as &$item) {
            if ($item['kind'] === 'field' || $item['kind'] === 'system') {
                $r = $paths->resolve($rt, $item['path']);
                $item['numeric'] = $r !== null && $this->definitions->isNumeric($r);
                $item['date'] = $r !== null && $this->definitions->isDate($r);
            }
        }
        unset($item);

        return response()->json(['data' => $items]);
    }

    public function show(string $report): JsonResponse
    {
        Gate::authorize('system.manage_reports');

        return response()->json(['data' => $this->definitions->present($this->find($report))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $p = $this->definitions->check((array) $request->input('report', []), null);
        $r = $this->definitions->save(null, $p, $this->user());

        return response()->json(['data' => $this->definitions->present($r)], 201);
    }

    public function update(Request $request, string $report): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $r = $this->find($report);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->definitions->present($r)['hash'], (string) $request->input('base_hash')), 409, __('reports.changed_meanwhile'));
        $p = $this->definitions->check((array) $request->input('report', []), $r);
        $r = $this->definitions->save($r, $p, $this->user());

        return response()->json(['data' => $this->definitions->present($r)]);
    }

    public function destroy(string $report): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $this->definitions->delete($this->find($report), $this->user());

        return response()->json(null, 204);
    }

    /** Runs a definition that is being built, as the person building it, without saving it. */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $p = $this->definitions->check((array) $request->input('report', []) + ['key' => 'preview_only'], null);
        $rt = $this->runtimeFor($p['form']);

        return response()->json(['data' => $this->engine->run($rt, $p['definition'], $this->user())]);
    }

    /** The report's result for the person viewing it. */
    public function data(string $report): JsonResponse
    {
        $r = $this->find($report);
        $rt = $this->viewable($r);
        $doc = $this->definitions->present($r);

        return response()->json(['data' => [
            'uuid' => $doc['uuid'], 'label' => $doc['label'], 'description' => $this->translator->pick($r->translationsFor('description')),
            'visualization' => $r->visualization, 'options' => $doc['options'],
            'result' => $this->engine->run($rt, $r->definition, $this->user(), $doc['uuid'].':'.$doc['hash'], (int) ($r->cache_ttl_seconds ?? 0)),
        ]]);
    }

    public function export(Request $request, string $report, ReportExporter $exporter): BinaryFileResponse
    {
        $data = $request->validate(['format' => ['required', Rule::in(['xlsx', 'csv', 'pdf'])], 'image' => ['sometimes', 'nullable', 'string', 'max:2800000']]);
        $r = $this->find($report);
        $rt = $this->viewable($r);
        $doc = $this->definitions->present($r);
        $result = $this->engine->run($rt, $r->definition, $this->user(), $doc['uuid'].':'.$doc['hash'], (int) ($r->cache_ttl_seconds ?? 0));
        [$header, $rows] = ReportExporter::table($result, $r->visualization);
        $path = (string) tempnam(sys_get_temp_dir(), 'lcfrep');
        match ($data['format']) {
            'csv' => $exporter->csv($header, $rows, $path),
            'pdf' => $exporter->pdf($doc['label'], [['title' => $doc['label'], 'header' => $header, 'rows' => $rows, 'image' => ReportExporter::chartImage($data['image'] ?? null)]], $path),
            default => $exporter->xlsx([['title' => $doc['label'], 'header' => $header, 'rows' => $rows]], $path),
        };
        $this->audit->record('report.exported', 'export', null, 'report', $r->id, ['format' => $data['format'], 'rows' => count($rows)], $this->user()->id);

        return response()->download($path, (Str::slug($doc['label']) ?: 'report').'.'.$data['format'])->deleteFileAfterSend();
    }

    private function viewable(Report $r): FormRuntime
    {
        $user = $this->user();
        abort_unless($this->access->allows($user, 'report.'.strtolower($r->uuid).'.view'), 403);
        $form = Form::query()->find($r->base_form_id);
        abort_if($form === null, 404);
        abort_unless($form->kind === 'collection' || $this->access->allows($user, 'form.'.strtolower($form->uuid).'.view'), 403);

        return $this->runtimeFor($form);
    }

    private function runtimeFor(Form $form): FormRuntime
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null, 404);

        return $rt;
    }

    private function find(string $uuid): Report
    {
        $r = Str::isUuid($uuid) ? Report::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($r === null, 404);

        return $r;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
