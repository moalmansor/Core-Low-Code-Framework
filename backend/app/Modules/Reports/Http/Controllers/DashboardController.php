<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Reports\Dashboards;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\ReportDefinitions;
use App\Modules\Reports\ReportEngine;
use App\Modules\Reports\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Dashboards (specification §4.18, ADR-0053). Building them needs "Manage
 * reports and dashboards"; viewing one needs `dashboard.{uuid}.view`, and
 * each widget is shown only to the roles chosen for it and, for a report,
 * only to people who may view that report.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly Dashboards $dashboards,
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

        return response()->json(['data' => Dashboard::query()->orderBy('key')->get()->map(fn (Dashboard $d) => [
            'uuid' => strtolower($d->uuid), 'key' => $d->key, 'label' => $this->translator->labelOf($d->translationsFor('name'), $d->key), 'active' => $d->is_active,
            'widgets' => DB::table('dashboard_widgets')->where('dashboard_id', $d->id)->count(),
        ])->values()->all()]);
    }

    public function show(string $dashboard): JsonResponse
    {
        Gate::authorize('system.manage_reports');

        return response()->json(['data' => $this->dashboards->present($this->find($dashboard))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $d = $this->dashboards->save(null, $this->dashboards->check((array) $request->input('dashboard', []), null), $this->user());

        return response()->json(['data' => $this->dashboards->present($d)], 201);
    }

    public function update(Request $request, string $dashboard): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $d = $this->find($dashboard);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->dashboards->present($d)['hash'], (string) $request->input('base_hash')), 409, __('reports.changed_meanwhile'));
        $d = $this->dashboards->save($d, $this->dashboards->check((array) $request->input('dashboard', []), $d), $this->user());

        return response()->json(['data' => $this->dashboards->present($d)]);
    }

    public function destroy(string $dashboard): JsonResponse
    {
        Gate::authorize('system.manage_reports');
        $this->dashboards->delete($this->find($dashboard), $this->user());

        return response()->json(null, 204);
    }

    /** The dashboard as the viewer sees it: their widgets, each with its result. */
    public function view(string $dashboard): JsonResponse
    {
        [$d, $doc] = $this->viewable($dashboard);

        return response()->json(['data' => [
            'uuid' => $doc['uuid'], 'label' => $doc['label'], 'description' => $this->translator->pick($d->translationsFor('description')),
            'widgets' => array_map(fn (array $w) => $this->widget($w), $this->dashboards->visibleWidgets($doc, $this->user())),
        ]]);
    }

    public function export(Request $request, string $dashboard, ReportExporter $exporter): BinaryFileResponse
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'images' => ['sometimes', 'array', 'max:40'], 'images.*' => ['nullable', 'string', 'max:2800000'],
        ]);
        [$d, $doc] = $this->viewable($dashboard);
        $sections = [];
        foreach ($this->dashboards->visibleWidgets($doc, $this->user()) as $w) {
            $shown = $this->widget($w);
            $title = (string) $shown['title'];
            if ($w['type'] === 'text') {
                if ($data['format'] === 'pdf') {
                    $sections[] = ['title' => $title, 'header' => [], 'rows' => [], 'text' => (string) $shown['text']];
                }

                continue;
            }
            if ($shown['error'] !== null) {
                continue;
            }
            [$header, $rows] = ReportExporter::table($shown['result'], (string) $shown['visualization']);
            $sections[] = ['title' => $title, 'header' => $header, 'rows' => $rows, 'image' => ReportExporter::chartImage($data['images'][$w['uuid']] ?? null)];
        }
        if ($sections === []) {
            throw ValidationException::withMessages(['dashboard' => __('reports.nothing_to_export')]);
        }
        $path = (string) tempnam(sys_get_temp_dir(), 'lcfdash');
        $data['format'] === 'pdf' ? $exporter->pdf($doc['label'], $sections, $path) : $exporter->xlsx($sections, $path);
        $this->audit->record('dashboard.exported', 'export', null, 'dashboard', $d->id, ['format' => $data['format'], 'widgets' => count($sections)], $this->user()->id);

        return response()->download($path, (Str::slug($doc['label']) ?: 'dashboard').'.'.$data['format'])->deleteFileAfterSend();
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array<string, mixed>
     */
    private function widget(array $w): array
    {
        $out = ['uuid' => $w['uuid'], 'type' => $w['type'], 'layout' => $w['layout'], 'title' => $this->translator->pick((array) $w['i18n']['title']),
            'text' => $w['type'] === 'text' ? $this->translator->pick((array) $w['i18n']['text']) : null, 'result' => null, 'visualization' => null, 'options' => null, 'error' => null];
        if ($w['type'] !== 'report') {
            return $out;
        }
        $r = Report::query()->where('uuid', $w['report'])->first();
        $form = $r === null ? null : Form::query()->find($r->base_form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($r === null || $rt === null || ($form->kind !== 'collection' && ! $this->access->allows($this->user(), 'form.'.strtolower($form->uuid).'.view'))) {
            return ['error' => __('reports.not_available')] + $out;
        }
        $doc = $this->definitions->present($r);
        $out['title'] ??= $doc['label'];
        $out['visualization'] = $r->visualization;
        $out['options'] = $doc['options'];
        $out['report'] = $doc['uuid'];
        try {
            $out['result'] = $this->engine->run($rt, $r->definition, $this->user(), $doc['uuid'].':'.$doc['hash'], (int) ($r->cache_ttl_seconds ?? 0));
        } catch (ValidationException $e) {
            $out['error'] = (string) collect($e->errors())->flatten()->first();
        }

        return $out;
    }

    /** @return array{0: Dashboard, 1: array<string, mixed>} */
    private function viewable(string $uuid): array
    {
        $d = $this->find($uuid);
        abort_unless($d->is_active && $this->access->allows($this->user(), 'dashboard.'.strtolower($d->uuid).'.view'), 403);

        return [$d, $this->dashboards->present($d)];
    }

    private function find(string $uuid): Dashboard
    {
        $d = Str::isUuid($uuid) ? Dashboard::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($d === null, 404);

        return $d;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
