<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Http\Controllers;

use App\Modules\Downloads\DownloadJobs;
use App\Modules\Downloads\DownloadProfiles;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\RecordQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Running downloads (specification §4.23 "Usage"): the Download menu of the
 * records table and of the record page, a run with its format, parameters,
 * the user's table filters or selection when the profile allows them, and
 * following a run to its file.
 */
final class DownloadRunController extends Controller
{
    public function __construct(private readonly DownloadJobs $jobs, private readonly DownloadProfiles $profiles) {}

    public function index(Request $request, Form $form): JsonResponse
    {
        $data = $request->validate(['place' => ['required', Rule::in(DownloadProfiles::PLACES)]]);
        abort_if(! in_array($form->state, ['published', 'schema_inconsistent'], true), 404);

        return response()->json(['data' => $this->jobs->available($form, $this->user(), $data['place'])]);
    }

    public function run(Request $request, Form $form, string $profile): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(DownloadProfiles::FORMATS)],
            'parameters' => ['sometimes', 'array'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'uuids' => ['sometimes', 'array', 'max:5000'],
            'uuids.*' => ['uuid'],
            'record' => ['sometimes', 'nullable', 'uuid'],
        ] + self::filterRules());
        $p = $this->profiles->row($profile);
        abort_if($p === null || (int) $p->form_id !== $form->id || ! $this->jobs->mayUse($p, $this->user()), 404);
        abort_unless(in_array($data['format'], json_decode((string) $p->formats, true) ?: [], true), 422, __('downloads.bad_formats'));
        $job = $this->jobs->start($p, $this->user(), $data['format'], [
            'filters' => $data['filters'] ?? null, 'uuids' => $data['uuids'] ?? null, 'record' => $data['record'] ?? null,
            'parameters' => (array) ($data['parameters'] ?? []),
        ]);

        return response()->json(['data' => $this->jobs->present($job)], 201);
    }

    public function status(string $job): JsonResponse
    {
        return response()->json(['data' => $this->jobs->present($this->own($job))]);
    }

    public function cancel(string $job): JsonResponse
    {
        $row = $this->own($job);
        $this->jobs->cancel($row);

        return response()->json(['data' => $this->jobs->present($this->own($job))]);
    }

    public function retry(string $job): JsonResponse
    {
        $this->jobs->retry($this->own($job));

        return response()->json(['data' => $this->jobs->present($this->own($job))]);
    }

    /** @return array<string, mixed> the table filters a run may follow (as the records list sends them) */
    private static function filterRules(): array
    {
        $out = [];
        foreach (RecordQuery::rules() as $key => $rule) {
            $out['filters.'.$key] = $rule;
        }

        return $out + ['filters.view' => ['sometimes', 'nullable', 'uuid'], 'filters.vf' => ['sometimes', 'array']];
    }

    private function own(string $uuid): object
    {
        $row = DB::table('download_jobs')->where('uuid', strtolower($uuid))->first();
        abort_if($row === null || (int) $row->user_id !== $this->user()->id, 404);

        return $row;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
