<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Form, group and field access (specification §4.11, architecture §16.3).
 *
 * Levels: hidden < read_only < editable < required. Each candidate rule has a
 * specificity vector (target, status, mode, subject); rules are grouped into
 * tiers by full vector and walked from least to most specific: a tier's
 * allows replace the running value with the highest allowed level, its denies
 * cap the value within that tier; hard denies cap the result last. Without a
 * matching rule the system default applies (editable in create/edit,
 * read-only in view/print). The form-level permission gate then applies:
 * no `form.view` hides everything, no `form.edit` caps edit mode at
 * read-only, no `form.create` disables create mode.
 *
 * Results are cached per user, form and access epoch.
 */
final class FieldAccessResolver
{
    public const LEVELS = ['hidden' => 0, 'read_only' => 1, 'editable' => 2, 'required' => 3];

    public const MODES = ['create', 'edit', 'view', 'print'];

    private const SUBJECT_RANK = ['everyone' => 0, 'department' => 1, 'role' => 2, 'user' => 3];

    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    public function __construct(
        private readonly AccessResolver $permissions,
        private readonly AccessCache $cache,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Effective levels for one mode.
     *
     * @param  array<string, mixed>  $definition  published definition (groups, fields)
     * @return array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}
     */
    public function resolve(User $user, int $formId, string $formUuid, array $definition, string $mode, ?int $statusId = null): array
    {
        $key = implode(':', [$user->id, $formId, $definition['form']['version'] ?? 0, $mode, $statusId ?? 0]);
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }
        $cacheKey = sprintf('acc:%d:%d:%d:%s:%d:%s:%d', $this->tenant->organizationId(), $user->id, $formId, $definition['form']['version'] ?? 0, $this->cache->epoch(), $mode, $statusId ?? 0);

        return $this->memo[$key] = Cache::remember($cacheKey, 3600, fn (): array => $this->compute($this->subjects($user), $this->gate($user, $formUuid, $mode), $formId, $definition, $mode, $statusId));
    }

    /**
     * Effective levels for a role (preview "as role", architecture §22.2): the
     * role's own grants and access rules, as if held by a user with no
     * department and no other role.
     *
     * @param  array<string, mixed>  $definition
     * @return array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}
     */
    public function resolveForRole(int $roleId, int $formId, string $formUuid, array $definition, string $mode): array
    {
        return $this->resolveForSubject('role', $roleId, $formId, $formUuid, $definition, $mode);
    }

    /**
     * Effective levels for a bare subject (matrix cells): `everyone`, a
     * department (with its ancestors), or a role — each without any other
     * membership. Users are resolved with resolve().
     *
     * @param  array<string, mixed>  $definition
     * @return array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}
     */
    public function resolveForSubject(string $type, ?int $id, int $formId, string $formUuid, array $definition, string $mode, ?int $statusId = null): array
    {
        $subjects = ['departments' => [], 'roles' => [], 'user' => 0];
        if ($type === 'role' && $id !== null) {
            $subjects['roles'] = [$id];
        } elseif ($type === 'department' && $id !== null) {
            $subjects['departments'] = DB::table('department_closure')->where('descendant_id', $id)->pluck('ancestor_id')->map(static fn ($v) => (int) $v)->all();
        }
        $gate = [];
        foreach (['view', 'create', 'edit', 'delete', 'print'] as $ability) {
            $grants = DB::table('permission_assignments')
                ->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
                ->where('permissions.key', "form.{$formUuid}.{$ability}")
                ->where(static function ($q) use ($subjects): void {
                    $q->whereRaw('1 = 0');
                    if ($subjects['roles'] !== []) {
                        $q->orWhere(static fn ($w) => $w->where('subject_type', 'role')->whereIn('subject_id', $subjects['roles']));
                    }
                    if ($subjects['departments'] !== []) {
                        $q->orWhere(static fn ($w) => $w->where('subject_type', 'department')->whereIn('subject_id', $subjects['departments']));
                    }
                })
                ->where(static fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()->format('Y-m-d H:i:s.u')))
                ->get(['effect', 'subject_type'])->map(static fn ($r) => ['tier' => (string) $r->subject_type, 'effect' => (string) $r->effect])->all();
            [$gate[$ability]] = $this->permissions->decide($grants);
        }

        return $this->compute($subjects, $gate, $formId, $definition, $mode, $statusId);
    }

    /**
     * The explain-access trace for one target (architecture §16.7).
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function explain(User $user, int $formId, string $formUuid, array $definition, string $mode, ?string $fieldUuid, ?string $groupUuid = null, ?int $statusId = null): array
    {
        $ctx = $this->context($definition);
        $subjects = $this->subjects($user);
        $rules = $this->rules($formId, $subjects, $mode, $statusId);
        $chain = $fieldUuid !== null
            ? $this->chainForField($fieldUuid, $ctx)
            : ($groupUuid !== null ? $this->chainForGroup($groupUuid, $ctx) : [['type' => 'form', 'id' => null, 'level' => 0]]);
        [$value, $trace] = $this->walk($chain, $rules, $mode);
        $gate = $this->gate($user, $formUuid, $mode);
        $final = $this->applyGate($value, $gate, $mode);

        return [
            'mode' => $mode,
            'default' => $this->default($mode),
            'candidates' => $trace['candidates'],
            'tiers' => $trace['tiers'],
            'hard_denies' => $trace['hard'],
            'resolved' => $value,
            'gate' => $gate,
            'result' => $final,
            'decided_by' => $trace['decided_by'],
        ];
    }

    /**
     * @param  array{departments: list<int>, roles: list<int>, user: int}  $subjects
     * @param  array<string, bool>  $gate
     * @return array{form: string, groups: array<string, string>, fields: array<string, string>, modes: array<string, bool>}
     */
    private function compute(array $subjects, array $gate, int $formId, array $definition, string $mode, ?int $statusId): array
    {
        $ctx = $this->context($definition);
        $rules = $this->rules($formId, $subjects, $mode, $statusId);
        [$formValue] = $this->walk([['type' => 'form', 'id' => null, 'level' => 0]], $rules, $mode);
        $groups = [];
        foreach ($ctx['groups'] as $uuid => $g) {
            [$v] = $this->walk($this->chainForGroup($uuid, $ctx), $rules, $mode);
            $groups[$uuid] = $this->applyGate($v, $gate, $mode);
        }
        $fields = [];
        foreach ($ctx['fields'] as $uuid => $f) {
            [$v] = $this->walk($this->chainForField($uuid, $ctx), $rules, $mode);
            // A field inside a hidden or read-only group never exceeds it.
            foreach ($this->ancestors($f['group'], $ctx) as $g) {
                $v = $this->min($v, $groups[$g] ?? 'editable');
            }
            $fields[$uuid] = $this->applyGate($v, $gate, $mode);
        }

        return [
            'form' => $this->applyGate($formValue, $gate, $mode),
            'groups' => $groups,
            'fields' => $fields,
            'modes' => [
                'view' => $gate['view'],
                'create' => $gate['view'] && $gate['create'],
                'edit' => $gate['view'] && $gate['edit'],
                'delete' => $gate['view'] && $gate['delete'],
                'print' => $gate['view'] && $gate['print'],
            ],
        ];
    }

    /**
     * Candidate rules matching the user, for the mode (NULL = any mode).
     *
     * @param  array{departments: list<int>, roles: list<int>, user: int}  $subjects
     * @return list<array<string, mixed>>
     */
    private function rules(int $formId, array $subjects, string $mode, ?int $statusId): array
    {
        // Status overrides apply only in that status; rules without a status apply in every status.
        $rows = DB::table('field_access_rules')->where('form_id', $formId)
            ->where(static fn ($q) => $q->whereNull('mode')->orWhere('mode', $mode))
            ->where(static fn ($q) => $statusId === null ? $q->whereNull('status_id') : $q->whereNull('status_id')->orWhere('status_id', $statusId))
            ->where(static function ($q) use ($subjects): void {
                $q->where('subject_type', 'everyone')
                    ->orWhere(static fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $subjects['user']));
                if ($subjects['roles'] !== []) {
                    $q->orWhere(static fn ($w) => $w->where('subject_type', 'role')->whereIn('subject_id', $subjects['roles']));
                }
                if ($subjects['departments'] !== []) {
                    $q->orWhere(static fn ($w) => $w->where('subject_type', 'department')->whereIn('subject_id', $subjects['departments']));
                }
            })
            ->get();

        return $rows->map(static fn ($r): array => [
            'uuid' => strtolower((string) $r->uuid),
            'target_type' => $r->target_type,
            'group_id' => $r->group_id === null ? null : (int) $r->group_id,
            'field_id' => $r->field_id === null ? null : (int) $r->field_id,
            'subject_type' => $r->subject_type,
            'subject_id' => $r->subject_id === null ? null : (int) $r->subject_id,
            'mode' => $r->mode,
            'status' => $r->status_id === null ? null : (int) $r->status_id,
            'access' => $r->access,
            'effect' => $r->effect,
        ])->all();
    }

    /**
     * Applies the §16.2 tier walk over the rules that target any element of
     * the chain (form → ancestor groups → field).
     *
     * @param  list<array{type: string, id: int|null, level: int}>  $chain
     * @param  list<array<string, mixed>>  $rules
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function walk(array $chain, array $rules, string $mode): array
    {
        $candidates = [];
        foreach ($rules as $r) {
            foreach ($chain as $link) {
                $matches = match ($link['type']) {
                    'form' => $r['target_type'] === 'form',
                    'group' => $r['target_type'] === 'group' && $r['group_id'] === $link['id'],
                    default => $r['target_type'] === 'field' && $r['field_id'] === $link['id'],
                };
                if ($matches) {
                    $candidates[] = $r + ['vector' => [$link['level'], $r['status'] === null ? 0 : 1, $r['mode'] === null ? 0 : 1, self::SUBJECT_RANK[$r['subject_type']]]];
                }
            }
        }
        $value = $this->default($mode);
        $decidedBy = 'default';
        $tiers = [];
        $byVector = [];
        foreach ($candidates as $c) {
            if ($c['effect'] !== 'hard_deny') {
                $byVector[implode('.', array_map(static fn (int $n) => str_pad((string) $n, 4, '0', STR_PAD_LEFT), $c['vector']))][] = $c;
            }
        }
        ksort($byVector, SORT_STRING);
        foreach ($byVector as $vec => $tier) {
            $allows = array_filter($tier, static fn ($c) => $c['effect'] === 'allow');
            $denies = array_filter($tier, static fn ($c) => $c['effect'] === 'deny');
            if ($allows !== []) {
                $value = array_reduce($allows, fn (string $carry, array $c): string => $this->max($carry, $c['access']), 'hidden');
                $decidedBy = 'allow:'.$vec;
            }
            foreach ($denies as $d) {
                $capped = $this->min($value, $d['access']);
                if ($capped !== $value) {
                    $decidedBy = 'deny:'.$vec;
                }
                $value = $capped;
            }
            $tiers[] = ['vector' => $tier[0]['vector'], 'rules' => array_column($tier, 'uuid'), 'value_after' => $value];
        }
        $hard = array_values(array_filter($candidates, static fn ($c) => $c['effect'] === 'hard_deny'));
        foreach ($hard as $h) {
            $capped = $this->min($value, $h['access']);
            if ($capped !== $value) {
                $decidedBy = 'hard_deny';
            }
            $value = $capped;
        }

        return [$value, ['candidates' => $candidates, 'tiers' => $tiers, 'hard' => $hard, 'decided_by' => $decidedBy]];
    }

    /** @return array{view: bool, create: bool, edit: bool, delete: bool, print: bool} */
    private function gate(User $user, string $formUuid, string $mode): array
    {
        $p = fn (string $ability): bool => $this->permissions->allows($user, "form.{$formUuid}.{$ability}");

        return ['view' => $p('view'), 'create' => $p('create'), 'edit' => $p('edit'), 'delete' => $p('delete'), 'print' => $p('print')];
    }

    /** @param  array<string, bool>  $gate */
    private function applyGate(string $value, array $gate, string $mode): string
    {
        if (! $gate['view']) {
            return 'hidden';
        }
        if ($mode === 'edit' && ! $gate['edit']) {
            return $this->min($value, 'read_only');
        }
        if ($mode === 'create' && ! $gate['create']) {
            return $this->min($value, 'read_only');
        }
        if (in_array($mode, ['view', 'print'], true)) {
            return $this->min($value, 'read_only');
        }

        return $value;
    }

    private function default(string $mode): string
    {
        return in_array($mode, ['create', 'edit'], true) ? 'editable' : 'read_only';
    }

    /** @return array{departments: list<int>, roles: list<int>, user: int} */
    private function subjects(User $user): array
    {
        $departments = $user->department_id === null ? [] : DB::table('department_closure')->where('descendant_id', $user->department_id)->pluck('ancestor_id')->map(static fn ($id) => (int) $id)->all();

        return [
            'departments' => $departments,
            'roles' => $user->activeRoles()->pluck('roles.id')->map(static fn ($id) => (int) $id)->all(),
            'user' => (int) $user->id,
        ];
    }

    /**
     * Group/field ids and parents of a definition (ids looked up by uuid once).
     *
     * @param  array<string, mixed>  $definition
     * @return array{groups: array<string, array{id: int|null, parent: string|null}>, fields: array<string, array{id: int|null, group: string|null}>}
     */
    private function context(array $definition): array
    {
        $groupIds = DB::table('field_groups')->whereIn('uuid', array_column($definition['groups'] ?? [], 'uuid'))->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $uuid) => [strtolower((string) $uuid) => (int) $id])->all();
        $fieldIds = DB::table('fields')->whereIn('uuid', array_column($definition['fields'] ?? [], 'uuid'))->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $uuid) => [strtolower((string) $uuid) => (int) $id])->all();
        $groups = [];
        foreach ($definition['groups'] ?? [] as $g) {
            $groups[$g['uuid']] = ['id' => $groupIds[$g['uuid']] ?? null, 'parent' => $g['parent']];
        }
        $fields = [];
        foreach ($definition['fields'] ?? [] as $f) {
            $fields[$f['uuid']] = ['id' => $fieldIds[$f['uuid']] ?? null, 'group' => $f['group']];
        }

        return ['groups' => $groups, 'fields' => $fields];
    }

    /** @return list<string> group uuids from the outermost ancestor to the given group */
    private function ancestors(?string $groupUuid, array $ctx): array
    {
        $out = [];
        $seen = [];
        while ($groupUuid !== null && isset($ctx['groups'][$groupUuid]) && ! isset($seen[$groupUuid])) {
            $seen[$groupUuid] = true;
            array_unshift($out, $groupUuid);
            $groupUuid = $ctx['groups'][$groupUuid]['parent'];
        }

        return $out;
    }

    /** @return list<array{type: string, id: int|null, level: int}> */
    private function chainForGroup(string $groupUuid, array $ctx): array
    {
        $chain = [['type' => 'form', 'id' => null, 'level' => 0]];
        foreach ($this->ancestors($groupUuid, $ctx) as $depth => $g) {
            $chain[] = ['type' => 'group', 'id' => $ctx['groups'][$g]['id'], 'level' => 1 + $depth];
        }

        return $chain;
    }

    /** @return list<array{type: string, id: int|null, level: int}> */
    private function chainForField(string $fieldUuid, array $ctx): array
    {
        $chain = $this->chainForGroup($ctx['fields'][$fieldUuid]['group'] ?? '', $ctx);
        $chain[] = ['type' => 'field', 'id' => $ctx['fields'][$fieldUuid]['id'] ?? null, 'level' => 100];

        return $chain;
    }

    private function max(string $a, string $b): string
    {
        return self::LEVELS[$a] >= self::LEVELS[$b] ? $a : $b;
    }

    private function min(string $a, string $b): string
    {
        return self::LEVELS[$a] <= self::LEVELS[$b] ? $a : $b;
    }
}
