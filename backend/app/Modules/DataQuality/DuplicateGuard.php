<?php

declare(strict_types=1);

namespace App\Modules\DataQuality;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RecycleBin;
use Illuminate\Support\Facades\DB;

/**
 * Duplicate rules applied while a record is saved (specification §4.35,
 * ADR-0044). A "block" rule refuses the save from any source (screen, API,
 * import, automation). A "warn" rule asks the person saving on the screen or
 * through the API, who may save anyway (`confirm_duplicates`); saves nobody
 * is watching (imports, automations, actions) go ahead. The answer lists
 * the duplicates the person may see, and only counts the others.
 */
final class DuplicateGuard
{
    /** Interactive sources, where a warning can be answered. */
    private const ASKS = ['ui', 'api'];

    private bool $acknowledged = false;

    public function __construct(
        private readonly DuplicateFinder $finder,
        private readonly RecordStore $store,
        private readonly RecordScope $scope,
        private readonly AccessResolver $access,
        private readonly Translator $translator,
        private readonly RecycleBin $bin,
    ) {}

    /** The person saving has seen the warning and saves anyway (for this request). */
    public function acknowledge(bool $yes): void
    {
        $this->acknowledged = $yes;
    }

    /**
     * @param  array<string, mixed>  $values  the record's values as they will be saved
     *
     * @throws RecordException 409 `duplicate`
     */
    public function check(FormRuntime $rt, User $user, array $values, ?int $recordId, string $operation, string $source): void
    {
        $rules = DB::table('duplicate_rules')->where('form_id', $rt->form->id)->where('is_active', true)
            ->whereIn('applies_on', [$operation, 'both'])->orderBy('id')->get();
        if ($rules->isEmpty()) {
            return;
        }
        $asks = in_array($source, self::ASKS, true);
        $names = $this->translator->many('duplicate_rule', $rules->pluck('id')->map(static fn ($v) => (int) $v)->all(), ['name', 'message']);
        $hits = [];
        $blocking = false;
        foreach ($rules as $r) {
            if ($r->action === 'warn' && (! $asks || $this->acknowledged)) {
                continue;
            }
            $ids = $this->finder->find($rt, DuplicateRules::matcher($r), $values, $recordId);
            if ($ids === []) {
                continue;
            }
            $blocking = $blocking || $r->action === 'block';
            $hits[] = ['rule' => $r, 'ids' => $ids, 'message' => $names[(int) $r->id]['message'] ?? null, 'name' => $names[(int) $r->id]['name'] ?? null];
        }
        if ($hits === []) {
            return;
        }
        $ids = array_values(array_unique(array_merge(...array_column($hits, 'ids'))));
        $visible = [];
        $hidden = 0;
        $mayView = $this->access->allows($user, "form.{$rt->form->uuid}.view");
        foreach ($ids as $id) {
            if ($mayView && $this->scope->allows($rt, $user, 'view', $id) && ($found = $this->store->findById($rt, $id)) !== null) {
                $visible[] = ['uuid' => $found['uuid'], 'number' => $found['system']['record_number'] ?? null, 'title' => $this->bin->title($rt, DB::table($rt->table)->where('id', $id)->first())];
            } else {
                $hidden++;
            }
        }
        $message = $hits[0]['message'] ?? __($blocking ? 'data_quality.duplicate_blocked' : 'data_quality.duplicate_found');
        throw new RecordException(409, 'duplicate', (string) $message, [
            'duplicates' => $visible, 'hidden' => $hidden, 'blocking' => $blocking,
            'rules' => array_values(array_filter(array_map(static fn ($h) => $h['name'], $hits))),
        ]);
    }
}
