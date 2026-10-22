<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Expressions\Text\Unicode;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Global search (specification §4.29, ADR-0054). Per form, the admin chooses
 * whether it is searchable, which text fields are searched and how much each
 * one weighs, what a result shows under its title, and which roles may find
 * it. Searching respects everything else: the person needs the form's view
 * permission, finds only records in their record scope, and is matched only
 * on fields they may see. Sensitive and encrypted fields are never searched.
 */
final class GlobalSearch
{
    private const MAX_FIELDS = 10;

    private const PER_FORM = 5;

    private const MAX_FORMS = 25;

    private const TEXT_COLUMNS = ['string', 'code', 'text'];

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordScope $scope,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly DatabaseDriver $driver,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * The fields of a form that can be searched.
     *
     * @return list<array{uuid: string, key: string, label: string}>
     */
    public function searchable(FormRuntime $rt): array
    {
        $out = [];
        foreach ($rt->mainFields() as $f) {
            $col = $rt->column($f['uuid']);
            if ($col === null || ($col['encrypted'] ?? false) || ! in_array($col['type'], self::TEXT_COLUMNS, true) || ($f['flags']['sensitive'] ?? false)) {
                continue;
            }
            $out[] = ['uuid' => $f['uuid'], 'key' => (string) $f['key'], 'label' => $this->translator->labelOf($f['i18n']['label'] ?? null, (string) $f['key'])];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function present(Form $form): array
    {
        $row = DB::table('search_configs')->where('form_id', $form->id)->first();
        $rt = $this->runtimes->forForm($form);

        return [
            'form' => strtolower((string) $form->uuid),
            'form_name' => $this->translator->labelOf($form->translationsFor('name'), (string) $form->key),
            'published' => $form->state === 'published',
            'enabled' => $row !== null && (bool) $row->is_enabled,
            'fields' => $row === null ? [] : array_values((array) json_decode((string) $row->fields, true)),
            'subtitle' => $row === null ? null : (json_decode((string) $row->result_template, true)['subtitle'] ?? null),
            'roles' => $row === null ? [] : DB::table('roles')->whereIn('id', (array) json_decode((string) ($row->role_scope_ids ?? '[]'), true))->orderBy('id')
                ->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->values()->all(),
            'available' => $rt === null ? [] : $this->searchable($rt),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     *
     * @throws ValidationException
     */
    public function save(Form $form, array $d, User $actor): void
    {
        $rt = $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['form' => __('experience.form_not_published')]);
        }
        $allowed = array_column($this->searchable($rt), 'uuid');
        $errors = [];
        $fields = [];
        foreach (array_slice(array_values((array) ($d['fields'] ?? [])), 0, self::MAX_FIELDS + 1) as $i => $f) {
            $uuid = strtolower((string) ($f['field'] ?? ''));
            if (! in_array($uuid, $allowed, true)) {
                $errors["fields.{$i}"][] = __('experience.not_searchable');

                continue;
            }
            $fields[$uuid] = ['field' => $uuid, 'weight' => max(1, min(10, (int) ($f['weight'] ?? 1)))];
        }
        if (count($fields) > self::MAX_FIELDS) {
            $errors['fields'][] = __('reports.too_many', ['max' => self::MAX_FIELDS]);
        }
        $enabled = (bool) ($d['enabled'] ?? false);
        if ($enabled && $fields === []) {
            $errors['fields'][] = __('experience.search_fields_required');
        }
        $subtitle = ($d['subtitle'] ?? null) === null ? null : strtolower((string) $d['subtitle']);
        if ($subtitle !== null && ! in_array($subtitle, $allowed, true)) {
            $errors['subtitle'][] = __('experience.not_searchable');
        }
        $roles = [];
        foreach ((array) ($d['roles'] ?? []) as $r) {
            $id = is_string($r) && Str::isUuid($r) ? DB::table('roles')->where('uuid', strtolower($r))->value('id') : null;
            $id === null ? $errors['roles'][] = __('reports.unknown_role') : $roles[] = (int) $id;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $row = [
            'is_enabled' => $enabled, 'fields' => json_encode(array_values($fields)), 'result_template' => json_encode(['subtitle' => $subtitle]),
            'role_scope_ids' => $roles === [] ? null : json_encode(array_values(array_unique($roles))), 'updated_at' => $now, 'updated_by' => $actor->id,
        ];
        DB::transaction(function () use ($form, $row, $now, $actor, $enabled): void {
            if (DB::table('search_configs')->where('form_id', $form->id)->exists()) {
                DB::table('search_configs')->where('form_id', $form->id)->update($row);
            } else {
                DB::table('search_configs')->insert($row + ['form_id' => $form->id, 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $actor->id]);
            }
            $this->audit->record('search_config.updated', 'config', null, 'form', $form->id, ['enabled' => $enabled], $actor->id, formId: $form->id);
        });
    }

    /**
     * Records matching the term, across the forms the person may search,
     * best matches first.
     *
     * @return list<array<string, mixed>>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);
        $needle = mb_strtolower(Unicode::normalizeArabic($term));
        $roles = $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all();
        $configs = DB::table('search_configs')->where('is_enabled', true)->orderBy('form_id')->limit(200)->get();
        $results = [];
        $forms = 0;
        foreach ($configs as $c) {
            $scopeRoles = (array) json_decode((string) ($c->role_scope_ids ?? '[]'), true);
            if ($scopeRoles !== [] && array_intersect($scopeRoles, $roles) === []) {
                continue;
            }
            $form = Form::query()->find($c->form_id);
            if ($form === null || $form->state !== 'published' || ! $this->access->allows($user, 'form.'.strtolower((string) $form->uuid).'.view')) {
                continue;
            }
            $rt = $this->runtimes->forForm($form);
            if ($rt === null || ++$forms > self::MAX_FORMS) {
                continue;
            }
            $levels = $this->fieldAccess->resolve($user, $form->id, $form->uuid, $rt->definition, 'view')['fields'];
            $searchable = array_column($this->searchable($rt), null, 'uuid');
            $weighted = [];
            foreach ((array) json_decode((string) $c->fields, true) as $f) {
                $uuid = (string) ($f['field'] ?? '');
                // Only fields that are still searchable and that this person may see.
                if (isset($searchable[$uuid]) && ($levels[$uuid] ?? 'hidden') !== 'hidden' && ($col = $rt->column($uuid)) !== null) {
                    $weighted[] = ['uuid' => $uuid, 'key' => $searchable[$uuid]['key'], 'column' => $col['name'], 'weight' => (int) ($f['weight'] ?? 1)];
                }
            }
            if ($weighted === []) {
                continue;
            }
            $q = $this->scope->apply(DB::table($rt->table)->whereNull('deleted_at'), $rt, $user, 'view');
            $q->where(function ($w) use ($weighted, $term): void {
                foreach ($weighted as $f) {
                    $w->orWhere(fn ($x) => $this->driver->caseInsensitiveLike($x, $f['column'], $term));
                }
            });
            $raw = $q->orderByDesc('updated_at')->limit(self::PER_FORM * 4)->get()->map(static fn ($r) => (array) $r)->all();
            if ($raw === []) {
                continue;
            }
            $records = $this->store->hydrate($rt, $raw, false);
            $presented = $this->presenter->many($rt, $records, $levels, true);
            $subtitle = json_decode((string) $c->result_template, true)['subtitle'] ?? null;
            $subtitleKey = $subtitle !== null && isset($searchable[$subtitle]) && ($levels[$subtitle] ?? 'hidden') !== 'hidden' ? $searchable[$subtitle]['key'] : null;
            $hits = [];
            foreach ($records as $i => $rec) {
                $score = 0;
                foreach ($weighted as $f) {
                    $value = mb_strtolower(Unicode::normalizeArabic((string) (is_scalar($rec['values'][$f['key']] ?? null) ? $rec['values'][$f['key']] : '')));
                    if ($value === '' || ! str_contains($value, $needle)) {
                        continue;
                    }
                    $score += $f['weight'] * ($value === $needle ? 3 : (str_starts_with($value, $needle) ? 2 : 1));
                }
                $sub = $subtitleKey === null ? null : ($rec['values'][$subtitleKey] ?? null);
                $hits[] = [
                    'form' => ['uuid' => strtolower((string) $form->uuid), 'name' => $this->translator->labelOf($form->translationsFor('name'), (string) $form->key)],
                    'record' => ['uuid' => $rec['uuid'], 'title' => $presented[$i]['title'] ?? null, 'subtitle' => is_scalar($sub) ? (string) $sub : null],
                    'score' => max(1, $score),
                ];
            }
            usort($hits, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
            array_push($results, ...array_slice($hits, 0, self::PER_FORM));
        }
        usort($results, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, 30);
    }
}
