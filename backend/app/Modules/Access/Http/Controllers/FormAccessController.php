<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\FieldAccessRules;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\StepUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The form access matrix (specification §4.11, architecture §16.4): targets
 * (form, groups, fields) × subjects for one mode, each cell showing the
 * effective level and whether it is explicit or inherited; bulk edits write
 * only deviations; "reset to inherited" deletes the row. Explain access
 * traces one cell.
 */
final class FormAccessController extends Controller
{
    public function __construct(
        private readonly FieldAccessResolver $resolver,
        private readonly DraftRepository $drafts,
        private readonly Translator $translator,
    ) {}

    public function matrix(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'mode' => ['sometimes', Rule::in(FieldAccessResolver::MODES)],
            'subject_type' => ['sometimes', Rule::in(['everyone', 'role', 'department', 'user'])],
            'subjects' => ['sometimes', 'array', 'max:50'],
            'subjects.*' => ['uuid'],
            'group' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'nullable', 'uuid'],
            'deviating_only' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $mode = $data['mode'] ?? 'edit';
        $type = $data['subject_type'] ?? 'role';
        $statusId = $this->statusId($form, $data['status'] ?? null);
        $doc = $this->drafts->normalize($this->drafts->load($form));
        $definition = ['form' => $doc['form'] + ['version' => 0], 'groups' => $doc['groups'], 'fields' => $doc['fields']];

        // Subjects (columns), paged.
        $perPage = $data['per_page'] ?? 10;
        $page = $data['page'] ?? 1;
        $subjects = match ($type) {
            'everyone' => collect([['type' => 'everyone', 'id' => null, 'uuid' => null, 'name' => __('access.everyone')]]),
            'role' => DB::table('roles')->where('organization_id', $form->organization_id)->when(isset($data['subjects']), fn ($q) => $q->whereIn('uuid', $data['subjects']))->orderBy('sort_order')->get(['id', 'uuid'])
                ->map(fn ($r) => ['type' => 'role', 'id' => (int) $r->id, 'uuid' => strtolower((string) $r->uuid), 'name' => $this->translator->get('role', (int) $r->id, 'name') ?? '']),
            'department' => DB::table('departments')->whereNull('deleted_at')->when(isset($data['subjects']), fn ($q) => $q->whereIn('uuid', $data['subjects']))->orderBy('code')->get(['id', 'uuid', 'code'])
                ->map(fn ($d) => ['type' => 'department', 'id' => (int) $d->id, 'uuid' => strtolower((string) $d->uuid), 'name' => $this->translator->get('department', (int) $d->id, 'name') ?? $d->code]),
            default => User::query()->when(isset($data['subjects']), fn ($q) => $q->whereIn('uuid', $data['subjects']), fn ($q) => $q->whereIn('id', DB::table('field_access_rules')->where('form_id', $form->id)->where('subject_type', 'user')->select('subject_id')))
                ->orderBy('name')->get()->map(fn (User $u) => ['type' => 'user', 'id' => $u->id, 'uuid' => $u->uuid, 'name' => $u->name, 'model' => $u]),
        };
        $total = $subjects->count();
        $subjects = $subjects->slice(($page - 1) * $perPage, $perPage)->values();

        // Targets (rows): form, groups and fields in tree order, optionally one group's subtree.
        $targets = $this->targets($doc, $data['group'] ?? null);
        $ids = [
            'group' => DB::table('field_groups')->where('form_id', $form->id)->pluck('uuid', 'id')->mapWithKeys(static fn ($u, $id) => [(int) $id => strtolower((string) $u)])->all(),
            'field' => DB::table('fields')->where('form_id', $form->id)->pluck('uuid', 'id')->mapWithKeys(static fn ($u, $id) => [(int) $id => strtolower((string) $u)])->all(),
        ];
        $explicit = [];
        foreach (DB::table('field_access_rules')->where('form_id', $form->id)->where('subject_type', $type)->where(static fn ($q) => $q->whereNull('mode')->orWhere('mode', $mode))
            ->where(static fn ($q) => $statusId === null ? $q->whereNull('status_id') : $q->where('status_id', $statusId))->get() as $r) {
            $targetUuid = match ($r->target_type) {
                'form' => $form->uuid,
                'group' => $ids['group'][(int) $r->group_id] ?? null,
                default => $ids['field'][(int) $r->field_id] ?? null,
            };
            $explicit[$targetUuid][$r->subject_id === null ? '*' : (string) $r->subject_id][] = ['access' => $r->access, 'effect' => $r->effect, 'mode' => $r->mode];
        }
        $cells = [];
        foreach ($subjects as $s) {
            $levels = $s['type'] === 'user'
                ? $this->resolver->resolve($s['model'], $form->id, $form->uuid, $definition, $mode, $statusId)
                : $this->resolver->resolveForSubject($s['type'], $s['id'], $form->id, $form->uuid, $definition, $mode, $statusId);
            $col = $s['id'] === null ? '*' : (string) $s['id'];
            foreach ($targets as $t) {
                $effective = match ($t['type']) {
                    'form' => $levels['form'],
                    'group' => $levels['groups'][$t['uuid']] ?? null,
                    default => $levels['fields'][$t['uuid']] ?? null,
                };
                $cells[$t['uuid']][$s['uuid'] ?? 'everyone'] = ['effective' => $effective, 'explicit' => $explicit[$t['uuid']][$col] ?? []];
            }
        }
        if ($data['deviating_only'] ?? false) {
            $targets = array_values(array_filter($targets, static function (array $t) use ($cells): bool {
                foreach ($cells[$t['uuid']] ?? [] as $c) {
                    if ($c['explicit'] !== []) {
                        return true;
                    }
                }

                return false;
            }));
        }

        return response()->json(['data' => [
            'mode' => $mode,
            'status' => $data['status'] ?? null,
            'statuses' => DB::table('statuses')->where('form_id', $form->id)->whereNull('archived_at')->orderBy('sort_order')->get(['id', 'uuid', 'key', 'color'])
                ->map(fn ($st) => ['uuid' => strtolower((string) $st->uuid), 'key' => $st->key, 'color' => $st->color, 'name' => $this->translator->get('status', (int) $st->id, 'name') ?? $st->key])->values(),
            'subject_type' => $type,
            'subjects' => $subjects->map(static fn ($s) => ['type' => $s['type'], 'uuid' => $s['uuid'], 'name' => $s['name']])->values(),
            'targets' => $targets,
            'cells' => $cells,
        ], 'meta' => ['total_subjects' => $total, 'page' => $page, 'per_page' => $perPage]]);
    }

    /** Bulk upsert / reset of access rules. Hard denies need step-up confirmation. */
    public function update(Request $request, Form $form, FieldAccessRules $rules, StepUp $stepUp): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:1000'],
            'changes.*.target.type' => ['required', Rule::in(['form', 'group', 'field'])],
            'changes.*.target.uuid' => ['required', 'uuid'],
            'changes.*.subject.type' => ['required', Rule::in(['everyone', 'role', 'department', 'user'])],
            'changes.*.subject.uuid' => ['required_unless:changes.*.subject.type,everyone', 'nullable', 'uuid'],
            'changes.*.mode' => ['present', 'nullable', Rule::in(FieldAccessResolver::MODES)],
            'changes.*.access' => ['present', 'nullable', Rule::in(array_keys(FieldAccessResolver::LEVELS))],
            'changes.*.effect' => ['sometimes', Rule::in(['allow', 'deny', 'hard_deny'])],
            'changes.*.status' => ['sometimes', 'nullable', 'uuid'],
            'confirmation_code' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);
        if (collect($data['changes'])->contains(static fn ($c) => ($c['effect'] ?? 'allow') === 'hard_deny' && $c['access'] !== null)) {
            $stepUp->require($request, Auth::user());
        }
        DB::transaction(function () use ($data, $form, $rules): void {
            foreach ($data['changes'] as $i => $c) {
                $groupId = $c['target']['type'] === 'group' ? DB::table('field_groups')->where('form_id', $form->id)->where('uuid', $c['target']['uuid'])->value('id') : null;
                $fieldId = $c['target']['type'] === 'field' ? DB::table('fields')->where('form_id', $form->id)->where('uuid', $c['target']['uuid'])->value('id') : null;
                abort_if(($c['target']['type'] === 'group' && $groupId === null) || ($c['target']['type'] === 'field' && $fieldId === null) || ($c['target']['type'] === 'form' && $c['target']['uuid'] !== $form->uuid), 422, __('validation.exists', ['attribute' => "changes.{$i}.target"]));
                $subjectId = match ($c['subject']['type']) {
                    'everyone' => null,
                    'role' => DB::table('roles')->where('uuid', $c['subject']['uuid'])->value('id'),
                    'department' => DB::table('departments')->where('uuid', $c['subject']['uuid'])->value('id'),
                    default => DB::table('users')->where('uuid', $c['subject']['uuid'])->value('id'),
                };
                abort_if($c['subject']['type'] !== 'everyone' && $subjectId === null, 422, __('validation.exists', ['attribute' => "changes.{$i}.subject"]));
                $statusId = $this->statusId($form, $c['status'] ?? null);
                abort_if(($c['status'] ?? null) !== null && $statusId === null, 422, __('validation.exists', ['attribute' => "changes.{$i}.status"]));
                $rules->put($form, $c['target']['type'], $groupId === null ? null : (int) $groupId, $fieldId === null ? null : (int) $fieldId, $c['subject']['type'], $subjectId === null ? null : (int) $subjectId, $c['mode'], $c['access'], $c['effect'] ?? 'allow', $statusId);
            }
        });

        return response()->json(['data' => ['updated' => count($data['changes'])]]);
    }

    public function explain(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'user' => ['required', 'uuid', Rule::exists('users', 'uuid')],
            'field' => ['sometimes', 'nullable', 'uuid'],
            'group' => ['sometimes', 'nullable', 'uuid'],
            'mode' => ['sometimes', Rule::in(FieldAccessResolver::MODES)],
            'status' => ['sometimes', 'nullable', 'uuid'],
        ]);
        $user = User::query()->where('uuid', $data['user'])->firstOrFail();
        $doc = $this->drafts->normalize($this->drafts->load($form));
        $definition = ['form' => $doc['form'], 'groups' => $doc['groups'], 'fields' => $doc['fields']];
        $trace = $this->resolver->explain($user, $form->id, $form->uuid, $definition, $data['mode'] ?? 'edit', $data['field'] ?? null, $data['group'] ?? null, $this->statusId($form, $data['status'] ?? null));
        $subjectNames = [];
        foreach ($trace['candidates'] as &$c) {
            $c['subject_name'] = match ($c['subject_type']) {
                'everyone' => __('access.everyone'),
                'role' => $subjectNames['r'.$c['subject_id']] ??= ($this->translator->get('role', (int) $c['subject_id'], 'name') ?? ''),
                'department' => $subjectNames['d'.$c['subject_id']] ??= ($this->translator->get('department', (int) $c['subject_id'], 'name') ?? ''),
                default => $subjectNames['u'.$c['subject_id']] ??= (string) DB::table('users')->where('id', $c['subject_id'])->value('name'),
            };
            $c['target_key'] = match ($c['target_type']) {
                'form' => $form->key,
                'group' => DB::table('field_groups')->where('id', $c['group_id'])->value('key'),
                default => DB::table('fields')->where('id', $c['field_id'])->value('key'),
            };
        }
        unset($c);

        return response()->json(['data' => $trace]);
    }

    private function statusId(Form $form, ?string $uuid): ?int
    {
        $id = $uuid === null ? null : DB::table('statuses')->where('form_id', $form->id)->where('uuid', strtolower($uuid))->whereNull('archived_at')->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return list<array{type: string, uuid: string, key: string, label: string|null, depth: int, parent: string|null}> */
    private function targets(array $doc, ?string $onlyGroup): array
    {
        $out = [['type' => 'form', 'uuid' => $doc['form']['uuid'], 'key' => $doc['form']['key'], 'label' => $doc['form']['i18n']['name'][app()->getLocale()] ?? null, 'depth' => 0, 'parent' => null]];
        $children = [];
        foreach ($doc['groups'] as $g) {
            $children[$g['parent'] ?? ''][] = ['type' => 'group'] + $g;
        }
        foreach ($doc['fields'] as $f) {
            $children[$f['group'] ?? ''][] = ['type' => 'field'] + $f;
        }
        $walk = function (string $parent, int $depth, bool $include) use (&$walk, &$out, $children, $onlyGroup): void {
            $items = $children[$parent] ?? [];
            usort($items, static fn ($a, $b) => $a['order'] <=> $b['order']);
            foreach ($items as $item) {
                $in = $include || ($item['type'] === 'group' && $item['uuid'] === $onlyGroup);
                if ($in) {
                    $label = $item['type'] === 'group' ? ($item['i18n']['title'][app()->getLocale()] ?? null) : ($item['i18n']['label'][app()->getLocale()] ?? null);
                    $out[] = ['type' => $item['type'], 'uuid' => $item['uuid'], 'key' => $item['key'], 'label' => $label, 'depth' => $depth, 'parent' => $parent === '' ? null : $parent];
                }
                if ($item['type'] === 'group') {
                    $walk($item['uuid'], $depth + 1, $in);
                }
            }
        };
        $walk('', 1, $onlyGroup === null);

        return $out;
    }
}
