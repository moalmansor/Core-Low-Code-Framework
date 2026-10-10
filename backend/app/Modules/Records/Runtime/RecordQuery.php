<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Text\Unicode;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The record list query shared by the records table and the export: the
 * user's record scope, search over the normalized search text, simple
 * filters on filterable fields the reader can see, the trash for those who
 * may restore, and sorting on sortable fields.
 */
final class RecordQuery
{
    public function __construct(private readonly DatabaseDriver $driver, private readonly AccessResolver $access, private readonly References $refs, private readonly RecordScope $scope) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'filter' => ['sometimes', 'array', 'max:20'],
            'filter.*' => ['nullable'],
            'trashed' => ['sometimes', 'boolean'],
            // Selected records (export selected): narrows the result, never widens it.
            'uuids' => ['sometimes', 'array', 'max:1000'],
            'uuids.*' => ['uuid'],
        ];
    }

    /**
     * @param  array<string, string>  $levels  field uuid => access level
     * @param  array<string, mixed>  $data  validated by rules()
     */
    public function build(FormRuntime $rt, User $user, array $levels, array $data): Builder
    {
        $q = DB::table($rt->table);
        // Record-level security: every list, export and lookup goes through the user's scope.
        $this->scope->apply($q, $rt, $user, ($data['trashed'] ?? false) ? 'delete' : 'view');
        if (($data['trashed'] ?? false) && $this->access->allows($user, "form.{$rt->form->uuid}.restore")) {
            $q->whereNotNull('deleted_at');
        } else {
            $q->whereNull('deleted_at');
        }
        if (isset($data['uuids'])) {
            $q->whereIn('uuid', array_map('strtolower', $data['uuids']));
        }
        if (! empty($data['search'])) {
            $term = mb_strtolower(Unicode::normalizeArabic(trim((string) $data['search'])));
            $this->driver->caseInsensitiveLike($q, 'search_text', $term);
        }
        foreach ($data['filter'] ?? [] as $key => $value) {
            $uuid = $rt->keys[$key] ?? null;
            if ($uuid === null || ($levels[$uuid] ?? 'hidden') === 'hidden' || $value === null || $value === '') {
                continue;
            }
            $f = $rt->fields[$uuid];
            $col = $rt->column($uuid);
            if ($col === null || ($col['encrypted'] ?? false) || ! ($f['table']['filterable'] ?? false)) {
                continue;
            }
            // {op: contains|starts_with|equals, value} for text; {op: in, values: [...]} for choices and links.
            $op = is_array($value) && is_string($value['op'] ?? null) ? $value['op'] : null;
            if ($op === 'in') {
                $values = array_values(array_filter(array_slice((array) ($value['values'] ?? []), 0, 50), static fn ($v) => is_scalar($v) && $v !== ''));
                if ($values === []) {
                    continue;
                }
                if ($col['type'] === 'bigint' && ($table = $rt->targetTable($f)) !== null) {
                    $uuids = array_values(array_filter(array_map('strval', $values), static fn (string $v) => preg_match('/^[0-9a-f-]{36}$/i', $v) === 1));
                    $q->whereIn($col['name'], array_values($this->refs->ids($table, $uuids)) ?: [0]);
                } else {
                    $q->whereIn($col['name'], array_map('strval', $values));
                }

                continue;
            }
            if ($op !== null && in_array($op, ['contains', 'starts_with', 'equals'], true)) {
                $term = is_scalar($value['value'] ?? null) ? (string) $value['value'] : '';
                if ($term === '') {
                    continue;
                }
                if (in_array($col['type'], ['string', 'code', 'text'], true)) {
                    $this->driver->caseInsensitiveLike($q, $col['name'], $term, match ($op) {
                        'starts_with' => 'starts',
                        'equals' => 'equals',
                        default => 'contains',
                    });
                } elseif ($op === 'equals' && $col['type'] !== 'bigint') {
                    $q->where($col['name'], $term);
                }

                continue;
            }
            if ($col['type'] === 'bigint' && is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1 && ($table = $rt->targetTable($f)) !== null) {
                $q->where($col['name'], $this->refs->ids($table, [$value])[strtolower($value)] ?? 0);
            } elseif (is_array($value) && (isset($value['from']) || isset($value['to']))) {
                if (($value['from'] ?? null) !== null) {
                    $q->where($col['name'], '>=', $value['from']);
                }
                if (($value['to'] ?? null) !== null) {
                    $q->where($col['name'], '<=', $value['to']);
                }
            } elseif (in_array($col['type'], ['string', 'code', 'text'], true)) {
                $this->driver->caseInsensitiveLike($q, $col['name'], (string) $value);
            } elseif (is_scalar($value)) {
                $q->where($col['name'], $col['type'] === 'bool' ? (int) filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value);
            }
        }

        return $q;
    }

    /**
     * @param  array<string, string>  $levels
     * @param  array<string, mixed>  $data
     */
    public function order(Builder $q, FormRuntime $rt, array $levels, array $data): Builder
    {
        $sort = $data['sort'] ?? null;
        $direction = $data['direction'] ?? 'desc';
        $column = match (true) {
            $sort === null, $sort === 'updated_at' => 'updated_at',
            $sort === 'created_at' => 'created_at',
            $sort === 'record_number' => 'record_number',
            isset($rt->keys[$sort]) && ($levels[$rt->keys[$sort]] ?? 'hidden') !== 'hidden' && ($rt->fields[$rt->keys[$sort]]['table']['sortable'] ?? false) => $rt->column($rt->keys[$sort])['name'] ?? 'updated_at',
            default => 'updated_at',
        };

        return $q->orderBy($column, $direction)->orderBy('id', $direction);
    }
}
