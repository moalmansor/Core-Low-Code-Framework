<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Assignment\DelegationResolver;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Record-level security (specification §4.11 "Record level", architecture
 * §16.6). The scopes a user has on a form for an operation are resolved from
 * `record_access_rules` with the §16.2 tier walk, starting from the system
 * default "all records": everyone → department → role → user; a tier's
 * allows replace the scopes of the tiers below (and their denies), its
 * denies remove scopes, hard denies are applied last and cannot be
 * overridden. A deny with a custom condition excludes the records that match
 * it. The scopes become one WHERE clause that every record query goes
 * through; direct access outside it answers 404.
 */
final class RecordScope
{
    public const OPERATIONS = ['view', 'edit', 'delete'];

    public const SCOPES = ['none', 'own', 'own_department', 'department_tree', 'assigned', 'all', 'custom'];

    private const TIERS = ['everyone', 'department', 'role', 'user'];

    /** @var array<string, array{scopes: array<string, true>, custom: array<int, array<string, mixed>>, exclude: list<array<string, mixed>>, trace: list<array<string, mixed>>}> */
    private array $memo = [];

    public function __construct(
        private readonly AccessCache $cache,
        private readonly OwnedConditions $conditions,
        private readonly ScopePredicate $predicates,
        private readonly DelegationResolver $delegations,
    ) {}

    /**
     * The user's resolved scopes for one operation on a form.
     *
     * @return array{scopes: array<string, true>, custom: array<int, array<string, mixed>>, exclude: list<array<string, mixed>>, trace: list<array<string, mixed>>}
     */
    public function resolve(int $formId, User $user, string $op): array
    {
        $key = implode(':', [$formId, $user->id, $op, $this->cache->epoch()]);
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }
        $departments = $user->department_id === null ? [] : DB::table('department_closure')->where('descendant_id', $user->department_id)->pluck('ancestor_id')->map(static fn ($v) => (int) $v)->all();
        $roles = $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all();
        $rows = DB::table('record_access_rules')->where('form_id', $formId)->whereIn('operation', [$op, 'all'])
            ->where(static function ($q) use ($user, $departments, $roles): void {
                $q->where('subject_type', 'everyone')
                    ->orWhere(static fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $user->id));
                if ($roles !== []) {
                    $q->orWhere(static fn ($w) => $w->where('subject_type', 'role')->whereIn('subject_id', $roles));
                }
                if ($departments !== []) {
                    $q->orWhere(static fn ($w) => $w->where('subject_type', 'department')->whereIn('subject_id', $departments));
                }
            })->orderBy('priority')->orderBy('id')->get();
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());

        $scopes = ['all' => true];
        $custom = [];
        $exclude = [];
        $hardScopes = [];
        $hardExclude = [];
        $trace = [];
        foreach (self::TIERS as $tier) {
            $inTier = $rows->where('subject_type', $tier);
            if ($inTier->isEmpty()) {
                continue;
            }
            $allows = $inTier->where('effect', 'allow');
            if ($allows->isNotEmpty()) {
                $scopes = [];
                $custom = [];
                $exclude = [];
                foreach ($allows as $r) {
                    if ($r->scope === 'custom') {
                        if (isset($asts[(int) $r->condition_id])) {
                            $custom[(int) $r->id] = $asts[(int) $r->condition_id];
                        }
                    } else {
                        $scopes[$r->scope] = true;
                    }
                }
            }
            foreach ($inTier->whereIn('effect', ['deny', 'hard_deny']) as $r) {
                $hard = $r->effect === 'hard_deny';
                if ($r->scope === 'custom') {
                    if (isset($asts[(int) $r->condition_id])) {
                        $hard ? $hardExclude[] = $asts[(int) $r->condition_id] : $exclude[] = $asts[(int) $r->condition_id];
                    }
                } elseif ($hard) {
                    $hardScopes[$r->scope] = true;
                } else {
                    unset($scopes[$r->scope]);
                }
            }
            $trace[] = ['tier' => $tier, 'rules' => $inTier->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'scope' => $r->scope, 'effect' => $r->effect, 'operation' => $r->operation])->values()->all(), 'scopes' => array_keys($scopes)];
        }
        foreach (array_keys($hardScopes) as $s) {
            unset($scopes[$s]);
        }
        unset($scopes['none']);

        return $this->memo[$key] = ['scopes' => $scopes, 'custom' => $custom, 'exclude' => [...$exclude, ...$hardExclude], 'trace' => $trace];
    }

    /**
     * Restricts a record query to what the user (or a user they act for
     * under an active delegation) may reach for the operation.
     */
    public function apply(Builder $q, FormRuntime $rt, User $user, string $op, ?string $table = null): Builder
    {
        $table ??= $rt->table;
        $principals = $this->delegations->principalsFor($user, $rt->form->id);

        return $q->where(function (Builder $w) use ($principals, $rt, $op, $table): void {
            foreach ($principals as $p) {
                $w->orWhere(fn (Builder $x) => $this->predicate($x, $rt, $p, $op, $table));
            }
        });
    }

    /** Whether the record is within the user's scope for the operation. */
    public function allows(FormRuntime $rt, User $user, string $op, int $recordId): bool
    {
        return $this->apply(DB::table($rt->table)->where($rt->table.'.id', $recordId), $rt, $user, $op)->exists();
    }

    /**
     * Whether only a delegator's scope (not the user's own) reaches the
     * record: the id of that delegator, for on-behalf-of recording.
     */
    public function onBehalfOf(FormRuntime $rt, User $user, string $op, int $recordId): ?int
    {
        $principals = $this->delegations->principalsFor($user, $rt->form->id);
        if (count($principals) === 1) {
            return null;
        }
        $own = DB::table($rt->table)->where($rt->table.'.id', $recordId);
        if ($this->predicate($own, $rt, $user, $op, $rt->table)->exists()) {
            return null;
        }
        foreach (array_slice($principals, 1) as $p) {
            if ($this->predicate(DB::table($rt->table)->where($rt->table.'.id', $recordId), $rt, $p, $op, $rt->table)->exists()) {
                return $p->id;
            }
        }

        return null;
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function predicate(Builder $q, FormRuntime $rt, User $user, string $op, string $table): Builder
    {
        $r = $this->resolve($rt->form->id, $user, $op);
        if ($r['scopes'] === [] && $r['custom'] === []) {
            return $q->whereRaw('1 = 0');
        }
        if (! isset($r['scopes']['all'])) {
            $q->where(function (Builder $w) use ($r, $rt, $user, $table): void {
                foreach (array_keys($r['scopes']) as $scope) {
                    match ($scope) {
                        'own' => $w->orWhere(static fn (Builder $x) => $x->where($table.'.owner_user_id', $user->id)->orWhere($table.'.created_by', $user->id)),
                        'own_department' => $user->department_id === null ? $w->orWhereRaw('1 = 0') : $w->orWhere($table.'.owner_department_id', $user->department_id),
                        'department_tree' => $user->department_id === null ? $w->orWhereRaw('1 = 0')
                            : $w->orWhereIn($table.'.owner_department_id', DB::table('department_closure')->where('ancestor_id', $user->department_id)->select('descendant_id')),
                        'assigned' => $w->orWhereExists(function (Builder $x) use ($rt, $user, $table): void {
                            $roles = $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all();
                            $x->selectRaw('1')->from('assignments')
                                ->where('assignments.form_id', $rt->form->id)->whereColumn('assignments.record_id', $table.'.id')->where('assignments.status', 'active')
                                ->where(static function (Builder $s) use ($user, $roles): void {
                                    $s->where(static fn ($u) => $u->where('assignments.assignee_type', 'user')->where('assignments.assignee_id', $user->id));
                                    if ($roles !== []) {
                                        $s->orWhere(static fn ($u) => $u->where('assignments.assignee_type', 'role')->whereIn('assignments.assignee_id', $roles));
                                    }
                                    if ($user->department_id !== null) {
                                        $s->orWhere(static fn ($u) => $u->where('assignments.assignee_type', 'department')->where('assignments.assignee_id', $user->department_id));
                                    }
                                });
                        }),
                        default => null,
                    };
                }
                foreach ($r['custom'] as $ast) {
                    $w->orWhere(fn (Builder $x) => $this->predicates->apply($x, $rt, $ast, $user, $table));
                }
            });
        }
        foreach ($r['exclude'] as $ast) {
            // NOT EXISTS keeps records whose condition is false or unknown (empty values).
            $q->whereNotExists(fn (Builder $x) => $x->selectRaw('1')->from($rt->table.' as lcf_scope_x')->whereColumn('lcf_scope_x.id', $table.'.id')
                ->where(fn (Builder $y) => $this->predicates->apply($y, $rt, $ast, $user, 'lcf_scope_x', true)));
        }

        return $q;
    }
}
