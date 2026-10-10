<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\ExportService;
use App\Modules\Records\Exchange\ImportService;
use App\Modules\Records\Exchange\RecordExchange;
use App\Modules\Records\FileStore;
use App\Modules\Records\Jobs\RunExportJob;
use App\Modules\Records\Jobs\RunImportJob;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import and export of records (specification §4.15, architecture §19.5).
 * Imports: upload and inspect a file, map its columns (saved mappings),
 * preview the first rows, then start a background job (or a dry run),
 * follow its progress, cancel it, and download its error report. Exports:
 * start an export of what the table shows; small ones are ready at once,
 * larger ones run as a job. Import needs the form's import permission,
 * export its export permission; jobs and their files belong to the user
 * who started them.
 */
final class RecordExchangeController extends Controller
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly ImportService $imports,
        private readonly ExportService $exports,
    ) {}

    public function template(Form $form, RecordExchange $exchange): BinaryFileResponse
    {
        $rt = $this->runtime($form, 'import');

        return response()->download($exchange->template($rt, $this->user()), Str::slug($form->key).'-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Uploads the file to import and suggests a mapping. */
    public function inspect(Request $request, Form $form, FileStore $files): JsonResponse
    {
        $rt = $this->runtime($form, 'import');
        $request->validate(['file' => ['required', 'file']]);

        return $this->guarded(function () use ($rt, $request, $files, $form): JsonResponse {
            $file = $files->storeUpload($request->file('file'), ['xlsx', 'csv']);
            $data = $this->imports->inspect($rt, $this->user(), $file) + ['mappings' => $this->mappings($form)];

            return response()->json(['data' => $data]);
        });
    }

    public function preview(Request $request, Form $form): JsonResponse
    {
        $rt = $this->runtime($form, 'import');
        $data = $request->validate($this->mappingRules());

        return $this->guarded(function () use ($rt, $data): JsonResponse {
            $file = $this->upload($data['file']);
            $mapping = $this->imports->normalize($rt, $this->user(), $file, $data['mapping'], $data['mode'], $data['key_field'] ?? null);

            return response()->json(['data' => $this->imports->preview($rt, $this->user(), $file, $mapping, $data['mode'])]);
        });
    }

    public function start(Request $request, Form $form): JsonResponse
    {
        $rt = $this->runtime($form, 'import');
        $data = $request->validate($this->mappingRules() + [
            'dry_run' => ['sometimes', 'boolean'],
            'save_as' => ['sometimes', 'nullable', 'string', 'max:255'],
            'import_mapping' => ['sometimes', 'nullable', 'uuid'],
            'justification' => ['sometimes', 'nullable', 'array'],
        ]);

        return $this->guarded(function () use ($rt, $form, $data): JsonResponse {
            $user = $this->user();
            $file = $this->upload($data['file']);
            $mapping = $this->imports->normalize($rt, $user, $file, $data['mapping'], $data['mode'], $data['key_field'] ?? null);
            $savedId = isset($data['import_mapping']) ? DB::table('import_mappings')->where('uuid', $data['import_mapping'])->where('form_id', $form->id)->value('id') : null;
            if (($data['save_as'] ?? null) !== null && trim($data['save_as']) !== '') {
                $savedId = $this->saveMapping($form, $mapping, $data['mode'], trim($data['save_as']));
            }
            $job = $this->imports->start($rt, $user, $file, $mapping, $data['mode'], (bool) ($data['dry_run'] ?? false), $savedId === null ? null : (int) $savedId, $data['justification'] ?? null);

            return response()->json(['data' => $this->importJob($job)], 202);
        });
    }

    /** The user's recent imports into this form. */
    public function imports(Form $form): JsonResponse
    {
        $this->runtime($form, 'import');
        $jobs = DB::table('import_jobs')->where('form_id', $form->id)->where('user_id', $this->user()->id)->orderByDesc('id')->limit(20)->get();

        return response()->json(['data' => $jobs->map(fn (object $j) => $this->importJob($j))->values()]);
    }

    public function importStatus(string $job): JsonResponse
    {
        return response()->json(['data' => $this->importJob($this->ownImport($job))]);
    }

    public function cancelImport(string $job): JsonResponse
    {
        $row = $this->ownImport($job);
        abort_unless(in_array($row->status, ['queued', 'validating', 'running'], true), 409, __('records.import.not_cancellable'));
        $this->imports->cancel($row, $this->user());

        return response()->json(['data' => $this->importJob(DB::table('import_jobs')->where('id', $row->id)->first())]);
    }

    /** A short-lived link to the error report. */
    public function importReport(string $job): JsonResponse
    {
        $row = $this->ownImport($job);
        abort_if($row->error_report_file_id === null, 404, __('records.import.no_report'));

        return response()->json(['data' => ['url' => $this->fileUrl((int) $row->error_report_file_id)]]);
    }

    public function mappingsIndex(Form $form): JsonResponse
    {
        $this->runtime($form, 'import');

        return response()->json(['data' => $this->mappings($form)]);
    }

    public function deleteMapping(string $mapping): JsonResponse
    {
        $row = DB::table('import_mappings')->where('uuid', $mapping)->first();
        abort_if($row === null, 404);
        $form = Form::query()->findOrFail($row->form_id);
        $this->runtime($form, 'import');
        abort_unless((int) $row->created_by === $this->user()->id || $this->access->allows($this->user(), 'system.manage_forms'), 403, __('records.forbidden'));
        DB::table('import_jobs')->where('import_mapping_id', $row->id)->update(['import_mapping_id' => null]);
        DB::table('import_mappings')->where('id', $row->id)->delete();
        app(AuditWriter::class)->record('records.import_mapping_deleted', 'data', null, 'form', $form->id, ['mapping' => $row->uuid, 'name' => $row->name], null, null, $form->id);

        return response()->json(null, 204);
    }

    public function export(Request $request, Form $form): JsonResponse
    {
        $rt = $this->runtime($form, 'export');
        $data = $request->validate(RecordQuery::rules() + [
            'format' => ['required', Rule::in(ExportService::FORMATS)],
            'view' => ['sometimes', 'nullable', 'uuid'],
            'vf' => ['sometimes', 'array', 'max:30'],
            'columns' => ['sometimes', 'array', 'max:200'],
            'columns.*' => ['string', 'max:255'],
        ]);
        $format = $data['format'];
        unset($data['format']);
        $job = $this->exports->start($rt, $this->user(), $format, $data);

        return response()->json(['data' => $this->exportJob($job)], $job->queued ? 202 : 200);
    }

    public function exportStatus(string $job): JsonResponse
    {
        return response()->json(['data' => $this->exportJob($this->ownExport($job))]);
    }

    public function cancelExport(string $job): JsonResponse
    {
        $row = $this->ownExport($job);
        $this->exports->cancel($row);

        return response()->json(['data' => $this->exportJob(DB::table('export_jobs')->where('id', $row->id)->first())]);
    }

    /** Retries a failed import or export the user started (the Operations Center retries anyone's). */
    public function retry(string $kind, string $job): JsonResponse
    {
        $row = $kind === 'imports' ? $this->ownImport($job) : $this->ownExport($job);
        abort_unless($row->status === 'failed', 409);
        DB::table($kind === 'imports' ? 'import_jobs' : 'export_jobs')->where('id', $row->id)->update(['status' => 'queued', 'error' => null, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        $kind === 'imports' ? RunImportJob::dispatch((int) $row->id) : RunExportJob::dispatch((int) $row->id);

        return response()->json(['data' => ['status' => 'queued']], 202);
    }

    /** @return array<string, mixed> */
    private function importJob(object $j): array
    {
        return [
            'uuid' => strtolower((string) $j->uuid), 'status' => $j->status, 'mode' => $j->mode, 'dry_run' => (bool) $j->dry_run,
            'total_rows' => (int) $j->total_rows, 'processed_rows' => (int) $j->processed_rows,
            'progress' => (int) $j->total_rows === 0 ? ($j->status === 'completed' ? 100 : 0) : (int) floor(100 * (int) $j->processed_rows / (int) $j->total_rows),
            'created' => (int) $j->created_count, 'updated' => (int) $j->updated_count, 'errors' => (int) $j->error_count,
            'has_report' => $j->error_report_file_id !== null, 'error' => $j->error,
            'file' => DB::table('files')->where('id', $j->file_id)->value('original_name'),
            'created_at' => Carbon::parse($j->created_at, 'UTC')->toIso8601ZuluString(),
            'finished_at' => $j->finished_at === null ? null : Carbon::parse($j->finished_at, 'UTC')->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    private function exportJob(object $j): array
    {
        $ready = $j->status === 'completed' && $j->file_id !== null;

        return [
            'uuid' => strtolower((string) $j->uuid), 'status' => $j->status, 'format' => $j->format, 'progress' => (int) $j->progress,
            'rows' => $j->row_count === null ? null : (int) $j->row_count, 'error' => $j->error,
            'url' => $ready ? $this->fileUrl((int) $j->file_id) : null,
            'expires_at' => $j->expires_at === null ? null : Carbon::parse($j->expires_at, 'UTC')->toIso8601ZuluString(),
        ];
    }

    private function fileUrl(int $fileId): string
    {
        $uuid = (string) DB::table('files')->where('id', $fileId)->whereNull('deleted_at')->value('uuid');
        abort_if($uuid === '', 404, __('records.export.expired'));

        return URL::temporarySignedRoute('files.download', now()->addMinutes(5), ['file' => $uuid]);
    }

    private function ownImport(string $uuid): object
    {
        $row = DB::table('import_jobs')->where('uuid', strtolower($uuid))->first();
        abort_if($row === null || (int) $row->user_id !== $this->user()->id, 404);

        return $row;
    }

    private function ownExport(string $uuid): object
    {
        $row = DB::table('export_jobs')->where('uuid', strtolower($uuid))->first();
        abort_if($row === null || (int) $row->user_id !== $this->user()->id, 404);

        return $row;
    }

    /** The uploaded file of an import, owned by the user. */
    private function upload(string $uuid): StoredFile
    {
        $file = StoredFile::query()->where('uuid', strtolower($uuid))->whereNull('deleted_at')->first();
        abort_if($file === null || $file->uploaded_by !== $this->user()->id || ! in_array($file->extension, ['xlsx', 'csv'], true), 404);

        return $file;
    }

    /** @return list<array<string, mixed>> */
    private function mappings(Form $form): array
    {
        return DB::table('import_mappings')->where('form_id', $form->id)->orderBy('name')->get()->map(fn (object $m) => [
            'uuid' => strtolower((string) $m->uuid), 'name' => $m->name, 'mode' => $m->mode,
            'key_field' => $m->key_field_id === null ? null : strtolower((string) DB::table('fields')->where('id', $m->key_field_id)->value('uuid')),
            'mapping' => json_decode((string) $m->mapping, true),
            'mine' => (int) $m->created_by === $this->user()->id,
        ])->values()->all();
    }

    /**
     * Saves (or replaces, by name) a mapping: by column header, so it applies
     * to the next file with the same headers.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     */
    private function saveMapping(Form $form, array $mapping, string $mode, string $name): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $values = [
            'mapping' => json_encode(array_map(static fn ($c) => ['column_header' => $c['header'], 'field_uuid' => $c['field'], 'system' => $c['system'], 'transform' => $c['transform']], $mapping['columns']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'mode' => $mode,
            'key_field_id' => $mapping['key'] === null ? null : DB::table('fields')->where('uuid', $mapping['key']['uuid'])->value('id'),
            'updated_at' => $now, 'updated_by' => $this->user()->id,
        ];
        $existing = DB::table('import_mappings')->where('form_id', $form->id)->where('name', $name)->first();
        if ($existing !== null) {
            if ((int) $existing->created_by !== $this->user()->id && ! $this->access->allows($this->user(), 'system.manage_forms')) {
                throw new RecordException(422, 'mapping_name_taken', __('records.import.mapping.name_taken'), ['errors' => ['save_as' => [__('records.import.mapping.name_taken')]]]);
            }
            DB::table('import_mappings')->where('id', $existing->id)->update($values);

            return (int) $existing->id;
        }

        return (int) DB::table('import_mappings')->insertGetId($values + [
            'uuid' => (string) Str::uuid7(), 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $this->user()->id,
            'form_id' => $form->id, 'name' => $name,
        ]);
    }

    /** @return array<string, mixed> */
    private function mappingRules(): array
    {
        return [
            'file' => ['required', 'uuid'],
            'mode' => ['required', Rule::in(ImportService::MODES)],
            'key_field' => ['sometimes', 'nullable', 'uuid'],
            'mapping' => ['required', 'array', 'max:500'],
            'mapping.*.column' => ['required', 'integer', 'min:0', 'max:16383'],
            'mapping.*.field' => ['sometimes', 'nullable', 'uuid'],
            'mapping.*.system' => ['sometimes', 'nullable', Rule::in(['id', 'version'])],
            'mapping.*.transform' => ['sometimes', Rule::in(ImportService::TRANSFORMS)],
        ];
    }

    /** @param  callable(): JsonResponse  $work */
    private function guarded(callable $work): JsonResponse
    {
        try {
            return $work();
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }
    }

    private function runtime(Form $form, string $ability): FormRuntime
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null || $form->state !== 'published', 404, __('records.form_unavailable'));
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.view"), 404, __('records.form_unavailable'));
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.{$ability}"), 403, __('records.forbidden'));

        return $rt;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
