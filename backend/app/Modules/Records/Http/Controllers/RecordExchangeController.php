<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\RecordExchange;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel/CSV export and import of records (specification §4.8). Export needs
 * the form's export permission, import its import permission plus create or
 * edit for each row, checked by the record pipeline.
 */
final class RecordExchangeController extends Controller
{
    public function __construct(private readonly FormRuntimes $runtimes, private readonly AccessResolver $access, private readonly RecordExchange $exchange) {}

    public function export(Request $request, Form $form): BinaryFileResponse
    {
        $rt = $this->runtime($form, 'export');
        $data = $request->validate(RecordQuery::rules() + ['format' => ['sometimes', Rule::in(['xlsx', 'csv'])]]);
        $format = $data['format'] ?? 'xlsx';
        $result = $this->exchange->export($rt, $this->user(), $data, $format);

        return response()->download($result['path'], $this->fileName($form, $format), [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Export-Rows' => (string) $result['rows'],
            'X-Export-Truncated' => $result['truncated'] ? '1' : '0',
        ])->deleteFileAfterSend();
    }

    public function template(Form $form): BinaryFileResponse
    {
        $rt = $this->runtime($form, 'import');

        return response()->download($this->exchange->template($rt, $this->user()), $form->key.'-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function import(Request $request, Form $form, SettingsService $settings): JsonResponse
    {
        $rt = $this->runtime($form, 'import');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:'.((int) $settings->get('files', 'max_upload_mb') * 1024)],
            'commit' => ['sometimes', 'boolean'],
            'skip_invalid' => ['sometimes', 'boolean'],
        ]);
        try {
            $report = $this->exchange->import($rt, $this->user(), $request->file('file'), (bool) ($data['commit'] ?? false), (bool) ($data['skip_invalid'] ?? false));
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }

        return response()->json(['data' => $report]);
    }

    private function runtime(Form $form, string $ability): FormRuntime
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null || $form->state !== 'published', 404, __('records.form_unavailable'));
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.view"), 404, __('records.form_unavailable'));
        abort_unless($this->access->allows($this->user(), "form.{$form->uuid}.{$ability}"), 403, __('records.forbidden'));

        return $rt;
    }

    private function fileName(Form $form, string $format): string
    {
        return Str::slug($form->key).'-'.now('UTC')->format('Ymd-His').'.'.$format;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
