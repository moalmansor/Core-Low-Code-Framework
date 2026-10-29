<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Modules\Access\ScopePredicate;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legal holds (specification §4.26): a record, or every record of a form that
 * matches a condition, is exempt from deletion until the hold is lifted, with
 * the reason recorded both ways. Records under an active hold carry
 * `legal_hold` = 1, which deletes, the recycle bin purge, retention runs and
 * personal-data deletion all respect. The flag is recomputed whenever a hold
 * changes, before every retention run and nightly (so records that come to
 * match a condition are covered). A condition that cannot be evaluated holds
 * every record of the form: a hold fails safe.
 */
final class LegalHolds
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly OwnedConditions $conditions,
        private readonly ScopePredicate $predicate,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * Places a hold on one record (by id) or on the records matching a condition.
     *
     * @param  array<string, mixed>|null  $condition  expression AST
     */
    public function place(FormRuntime $rt, ?int $recordId, ?array $condition, string $reason, User $by): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = DB::transaction(function () use ($rt, $recordId, $condition, $reason, $by, $now): int {
            $id = (int) DB::table('legal_holds')->insertGetId([
                'uuid' => (string) Str::uuid7(), 'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now,
                'created_by' => $by->id, 'updated_by' => $by->id, 'form_id' => $rt->form->id, 'record_id' => $recordId, 'reason' => $reason, 'placed_at' => $now,
            ]);
            if ($condition !== null) {
                DB::table('legal_holds')->where('id', $id)->update(['condition_id' => $this->conditions->put($rt->form->id, 'legal_hold', $id, $condition, null)]);
            }

            return $id;
        });
        $held = $this->recompute($rt);
        $this->audit->record('legal_hold.placed', 'data', null, 'legal_hold', $id, ['form' => $rt->form->key, 'scope' => $recordId !== null ? 'record' : 'condition', 'reason' => $reason, 'records_held' => $held],
            $by->id, null, $rt->form->id, $recordId);

        return $id;
    }

    public function lift(object $hold, string $reason, User $by): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('legal_holds')->where('id', $hold->id)->whereNull('lifted_at')
            ->update(['lifted_at' => $now, 'lifted_by' => $by->id, 'lift_reason' => $reason, 'updated_at' => $now, 'updated_by' => $by->id]);
        $form = Form::query()->find((int) $hold->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $held = $rt === null ? 0 : $this->recompute($rt);
        $this->audit->record('legal_hold.lifted', 'data', null, 'legal_hold', (int) $hold->id, ['form' => $form?->key, 'reason' => $reason, 'records_held' => $held],
            $by->id, null, (int) $hold->form_id, $hold->record_id === null ? null : (int) $hold->record_id);
    }

    /**
     * Sets `legal_hold` on exactly the records an active hold covers and
     * clears it elsewhere, in one transaction. Returns how many are held.
     */
    public function recompute(FormRuntime $rt): int
    {
        $holds = DB::table('legal_holds')->where('form_id', $rt->form->id)->whereNull('lifted_at')->get(['id', 'record_id', 'condition_id', 'created_by']);
        if ($holds->isEmpty()) {
            DB::table($rt->table)->where('legal_hold', true)->update(['legal_hold' => false]);

            return 0;
        }

        return DB::transaction(function () use ($rt, $holds): int {
            $covered = function ($q) use ($rt, $holds): void {
                $q->where(function ($w) use ($rt, $holds): void {
                    foreach ($holds as $h) {
                        if ($h->record_id !== null) {
                            $w->orWhere('id', (int) $h->record_id);

                            continue;
                        }
                        $ast = $this->conditions->ast($h->condition_id === null ? null : (int) $h->condition_id);
                        $user = $h->created_by === null ? null : User::query()->find((int) $h->created_by);
                        if ($ast === null || $user === null) {
                            $w->orWhereRaw('1 = 1');

                            continue;
                        }
                        $w->orWhere(fn ($x) => $this->predicate->apply($x, $rt, $ast, $user, $rt->table, true));
                    }
                });
            };
            DB::table($rt->table)->where('legal_hold', true)->whereNot(fn ($q) => $covered($q))->update(['legal_hold' => false]);
            DB::table($rt->table)->where('legal_hold', false)->where(fn ($q) => $covered($q))->update(['legal_hold' => true]);

            return DB::table($rt->table)->where('legal_hold', true)->count();
        });
    }

    /** Recomputes every form that has a hold or a held record. */
    public function recomputeAll(): int
    {
        $n = 0;
        foreach (Form::query()->withoutGlobalScopes()->whereNotNull('current_version_id')->orderBy('id')->get() as $form) {
            app(TenantContext::class)->set($form->organization_id);
            $rt = $this->runtimes->forForm($form);
            if ($rt !== null && (DB::table('legal_holds')->where('form_id', $form->id)->exists() || DB::table($rt->table)->where('legal_hold', true)->exists())) {
                $n += $this->recompute($rt);
            }
        }

        return $n;
    }
}
