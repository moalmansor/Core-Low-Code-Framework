<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The recycle bin's retention (specification §4.35, ADR-0044). Every
 * deleted record — by a person, a bulk operation, a cascade or a merge — gets
 * a `recycle_bin` entry with the date it may be purged
 * (`data_quality.recycle_bin_days` after the delete). It can be restored
 * until then; afterwards the daily purge removes it for good, unless it is
 * under legal hold or something still points at it. Restores and purges are
 * audited.
 */
final class RecycleBin
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly FormRuntimes $runtimes,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
    ) {}

    /** Notes a record entering the bin; call inside the delete's transaction. */
    public function entered(FormRuntime $rt, int $recordId, ?int $userId, ?int $justificationId): void
    {
        $now = Carbon::now('UTC');
        $row = DB::table($rt->table)->where('id', $recordId)->first();
        DB::table('recycle_bin')->insert([
            'organization_id' => $rt->form->organization_id, 'form_id' => $rt->form->id, 'record_id' => $recordId,
            'title_snapshot' => mb_substr($this->title($rt, $row), 0, 255), 'deleted_by' => $userId, 'deleted_at' => $now->format('Y-m-d H:i:s.u'),
            'justification_id' => $justificationId,
            'purge_after' => $now->copy()->addDays((int) $this->settings->get('data_quality', 'recycle_bin_days'))->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** Notes a record leaving the bin by restore. */
    public function restored(FormRuntime $rt, int $recordId, ?int $userId): void
    {
        DB::table('recycle_bin')->where('form_id', $rt->form->id)->where('record_id', $recordId)->whereNull('restored_at')->whereNull('purged_at')
            ->update(['restored_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'restored_by' => $userId]);
    }

    /**
     * When deleted records will be purged, for the recycle bin list.
     *
     * @param  list<int>  $recordIds
     * @return array<int, string> record id => ISO time
     */
    public static function purgeDates(int $formId, array $recordIds): array
    {
        if ($recordIds === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('recycle_bin')->where('form_id', $formId)->whereIn('record_id', $recordIds)->whereNull('restored_at')->whereNull('purged_at')->get(['record_id', 'purge_after']) as $e) {
            $out[(int) $e->record_id] = Carbon::parse((string) $e->purge_after, 'UTC')->toIso8601ZuluString();
        }

        return $out;
    }

    /**
     * Removes the records whose retention has passed. A record under legal
     * hold, restored meanwhile, or still pointed at by another record (the
     * database refuses the removal) stays and is tried again next time.
     */
    public function purgeDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now('UTC');
        $purged = 0;
        $due = DB::table('recycle_bin')->whereNull('restored_at')->whereNull('purged_at')->where('purge_after', '<=', $now->format('Y-m-d H:i:s.u'))
            ->orderBy('id')->limit(500)->get();
        foreach ($due as $entry) {
            $form = Form::query()->withoutGlobalScopes()->find($entry->form_id);
            $rt = $form === null ? null : $this->runtimes->forForm($form);
            if ($rt === null) {
                continue;
            }
            $this->tenant->set((int) $entry->organization_id);
            $row = DB::table($rt->table)->where('id', $entry->record_id)->first(['id', 'deleted_at', 'legal_hold']);
            if ($row === null || $row->deleted_at === null) {
                // Already gone, or restored outside the bin: nothing to purge.
                DB::table('recycle_bin')->where('id', $entry->id)->update(['purged_at' => $now->format('Y-m-d H:i:s.u')]);

                continue;
            }
            if ((bool) $row->legal_hold) {
                continue;
            }
            try {
                DB::transaction(function () use ($rt, $entry, $now): void {
                    foreach ($rt->repeaters as $rep) {
                        $rows = DB::table($rep['table'])->where('parent_id', $entry->record_id)->pluck('id')->all();
                        foreach ($rt->pivots as $pivot) {
                            DB::table($pivot)->whereIn('source_id', [(int) $entry->record_id, ...$rows])->delete();
                        }
                        DB::table($rep['table'])->where('parent_id', $entry->record_id)->delete();
                    }
                    foreach ($rt->pivots as $pivot) {
                        DB::table($pivot)->where('source_id', $entry->record_id)->delete();
                    }
                    DB::table('files')->where('form_id', $rt->form->id)->where('record_id', $entry->record_id)->whereNull('deleted_at')
                        ->update(['deleted_at' => $now->format('Y-m-d H:i:s.u')]);
                    DB::table($rt->table)->where('id', $entry->record_id)->whereNotNull('deleted_at')->delete();
                    DB::table('recycle_bin')->where('id', $entry->id)->update(['purged_at' => $now->format('Y-m-d H:i:s.u')]);
                    $this->audit->record('record.purged', 'data', null, 'record', (int) $entry->record_id, ['form' => $rt->form->key, 'deleted_at' => $entry->deleted_at],
                        null, null, $rt->form->id, (int) $entry->record_id);
                });
                $purged++;
            } catch (QueryException $e) {
                // Something still points at it (another record, a pivot of another form): it stays.
                report($e);
            }
        }

        return $purged;
    }

    /**
     * How a record is named where it is listed by itself: its number, else its
     * first non-empty text value (never an encrypted one).
     *
     * @param  object|null  $row  the record's row
     */
    public function title(FormRuntime $rt, ?object $row): string
    {
        if ($row === null) {
            return '';
        }
        if (($row->record_number ?? null) !== null && (string) $row->record_number !== '') {
            return (string) $row->record_number;
        }
        foreach ($rt->mainFields() as $f) {
            $column = $rt->column($f['uuid']);
            $col = $column === null || ($column['encrypted'] ?? false) ? null : ($column['name'] ?? null);
            $v = $col === null ? null : ($row->{$col} ?? null);
            if (in_array($rt->type($f)?->storage, ['string', 'text'], true) && is_string($v) && trim($v) !== '') {
                return $v;
            }
        }

        return '';
    }
}
