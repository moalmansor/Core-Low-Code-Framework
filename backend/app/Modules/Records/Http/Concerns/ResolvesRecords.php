<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Concerns;

use App\Modules\Access\RecordScope;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Shared checks of record endpoints outside the record controller: the
 * published runtime, the form permission (directly or through a delegator)
 * and the record scope (404 outside it).
 */
trait ResolvesRecords
{
    protected function runtimeFor(Form $form, string $ability = 'view'): FormRuntime
    {
        $rt = app(FormRuntimes::class)->forForm($form);
        abort_if($rt === null || ! in_array($form->state, ['published', 'schema_inconsistent'], true), 404, __('records.form_unavailable'));
        $pipeline = app(RecordPipeline::class);
        abort_if($pipeline->permitted($rt, $this->currentUser(), ["form.{$form->uuid}.view"]) === false, 404, __('records.form_unavailable'));
        if ($ability !== 'view') {
            abort_if($pipeline->permitted($rt, $this->currentUser(), ["form.{$form->uuid}.{$ability}"]) === false, 403, __('records.forbidden'));
        }

        return $rt;
    }

    /**
     * @return array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}
     */
    protected function recordFor(FormRuntime $rt, string $uuid, string $op = 'view', bool $withDeleted = false): array
    {
        $found = app(RecordStore::class)->find($rt, strtolower($uuid), $withDeleted);
        abort_if($found === null || ! app(RecordScope::class)->allows($rt, $this->currentUser(), $op, $found['id']), 404, __('records.not_found'));

        return $found;
    }

    protected function runRecord(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }
    }

    protected function idempotencyKey(): string
    {
        $key = (string) request()->header('Idempotency-Key', '');

        return preg_match('/^[A-Za-z0-9-]{16,64}$/', $key) === 1 ? $key : (string) Str::uuid7();
    }

    protected function currentUser(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
