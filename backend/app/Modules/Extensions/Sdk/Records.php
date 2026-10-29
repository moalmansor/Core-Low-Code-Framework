<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Sdk;

use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Runtime\RecordQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reading records from an extension, as the person the extension acts for:
 * the same form permission, record scope and field access as the records
 * screens. Nothing here writes.
 */
final class Records
{
    public function __construct(private readonly User $user) {}

    /**
     * Records of a form, newest first, optionally matching a search.
     *
     * @return list<array<string, mixed>> each with uuid, title, values and status
     */
    public function list(string $formKey, ?string $search = null, int $limit = 25): array
    {
        $form = $this->form($formKey);
        if ($form === null) {
            return [];
        }
        $request = Request::create('/api/v1/r/'.$form->uuid, 'GET', array_filter(['search' => $search, 'per_page' => max(1, min(100, $limit))], static fn ($v) => $v !== null));

        return $this->as(fn () => (array) (app(RecordController::class)->index($request, $form, app(RecordQuery::class))->getData(true)['data'] ?? []));
    }

    /** @return array<string, mixed>|null one record, or null when it does not exist for this person */
    public function find(string $formKey, string $uuid): ?array
    {
        $form = $this->form($formKey);
        if ($form === null || preg_match('/^[0-9a-f-]{36}$/i', $uuid) !== 1) {
            return null;
        }

        return $this->as(function () use ($form, $uuid) {
            try {
                return app(RecordController::class)->show($form, strtolower($uuid))->getData(true)['data'] ?? null;
            } catch (HttpException) {
                return null;
            }
        });
    }

    private function form(string $key): ?Form
    {
        return Form::query()->where('key', strtolower($key))->whereIn('state', ['published', 'schema_inconsistent'])->first();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $run
     * @return T
     */
    private function as(callable $run): mixed
    {
        $previous = Auth::user();
        Auth::setUser($this->user);
        try {
            return $run();
        } catch (HttpException) {
            return [];
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
    }
}
