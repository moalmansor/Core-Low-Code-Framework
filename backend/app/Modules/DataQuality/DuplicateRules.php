<?php

declare(strict_types=1);

namespace App\Modules\DataQuality;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\DataQuality\Jobs\RunDuplicateSweep;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecycleBin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A form's duplicate rules (specification §4.35, ADR-0044): which fields
 * are compared and how, whether all or any must match, whether a match warns
 * or blocks, on creating, editing or both. Saved whole with a concurrency
 * hash. A sweep finds groups of duplicates among the existing records, in
 * the background, as a bulk operation whose result lists the groups.
 */
final class DuplicateRules
{
    public const METHODS = ['exact', 'normalized', 'fuzzy'];

    public const MAX_RULES = 20;

    public const MAX_FIELDS = 5;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly Translator $translator,
        private readonly DuplicateFinder $finder,
        private readonly AuditWriter $audit,
        private readonly CorrelationId $correlation,
        private readonly RecordScope $scope,
        private readonly AccessResolver $access,
        private readonly RecycleBin $bin,
    ) {}

    /**
     * A stored rule as the finder reads it.
     *
     * @return array{fields: list<array{field: string, method: string, threshold: float|null}>, mode: string}
     */
    public static function matcher(object $rule): array
    {
        $fields = [];
        foreach ((array) (json_decode((string) $rule->match_fields, true) ?: []) as $m) {
            $fields[] = ['field' => strtolower((string) ($m['field_uuid'] ?? '')), 'method' => (string) ($m['method'] ?? 'exact'), 'threshold' => isset($m['threshold']) ? (float) $m['threshold'] : null];
        }

        return ['fields' => $fields, 'mode' => (string) $rule->match_mode];
    }

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('duplicate_rules')->where('form_id', $form->id)->orderBy('id')->get();
        $tr = $this->translator->allMany('duplicate_rule', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $sweeps = DB::table('bulk_operations')->whereIn('id', $rows->pluck('last_sweep_operation_id')->filter()->all() ?: [0])->pluck('uuid', 'id');

        return $rows->map(static fn ($r) => [
            'uuid' => strtolower((string) $r->uuid),
            'i18n' => ['name' => (object) ($tr[(int) $r->id]['name'] ?? []), 'message' => (object) ($tr[(int) $r->id]['message'] ?? [])],
            'fields' => self::matcher($r)['fields'],
            'mode' => $r->match_mode,
            'action' => $r->action,
            'appliesOn' => $r->applies_on,
            'active' => (bool) $r->is_active,
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @return array<string, array{at: string|null, operation: string|null}> last sweep of each rule */
    public function sweeps(Form $form): array
    {
        $out = [];
        foreach (DB::table('duplicate_rules')->where('form_id', $form->id)->get(['uuid', 'last_sweep_at', 'last_sweep_operation_id']) as $r) {
            $op = $r->last_sweep_operation_id === null ? null : DB::table('bulk_operations')->where('id', $r->last_sweep_operation_id)->value('uuid');
            $out[strtolower((string) $r->uuid)] = ['at' => $r->last_sweep_at === null ? null : Carbon::parse((string) $r->last_sweep_at, 'UTC')->toIso8601ZuluString(),
                'operation' => $op === null ? null : strtolower((string) $op)];
        }

        return $out;
    }

    /** @param  list<array<string, mixed>>  $rules */
    public function save(Form $form, array $rules, int $userId): void
    {
        $rt = $this->runtimes->forForm($form) ?? throw ValidationException::withMessages(['rules' => __('data_quality.publish_first')]);
        $rules = $this->validate($rt, $rules);
        DB::transaction(function () use ($form, $rules, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            $tr = [];
            foreach ($rules as $r) {
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'match_mode' => $r['mode'], 'action' => $r['action'], 'applies_on' => $r['appliesOn'], 'is_active' => $r['active'],
                    'match_fields' => json_encode(array_map(static fn ($m) => ['field_uuid' => $m['field'], 'method' => $m['method'], 'threshold' => $m['threshold']], $r['fields'])),
                ];
                $existing = DB::table('duplicate_rules')->where('form_id', $form->id)->where('uuid', $r['uuid'])->first();
                if ($existing === null) {
                    $id = (int) DB::table('duplicate_rules')->insertGetId($row + ['uuid' => $r['uuid'], 'organization_id' => $form->organization_id, 'form_id' => $form->id, 'created_at' => $now, 'created_by' => $userId]);
                } else {
                    $id = (int) $existing->id;
                    DB::table('duplicate_rules')->where('id', $id)->update($row);
                }
                $tr[$id] = ['name' => $r['i18n']['name'], 'message' => $r['i18n']['message']];
                $kept[] = $id;
            }
            $this->translator->syncObjects('duplicate_rule', $tr);
            foreach (DB::table('duplicate_rules')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->pluck('id') as $gone) {
                $this->translator->forget('duplicate_rule', (int) $gone);
                DB::table('duplicate_rules')->where('id', $gone)->delete();
            }
            $this->audit->record('duplicate_rules.saved', 'config', null, 'form', $form->id, ['rules' => count($rules)], $userId, null, $form->id);
        });
    }

    /** Starts a sweep of one rule over the form's records (in the background). */
    public function startSweep(object $rule, User $user): object
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (int) DB::table('bulk_operations')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => (int) $rule->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'form_id' => (int) $rule->form_id, 'type' => 'duplicate_sweep', 'criteria' => json_encode(['rule' => strtolower((string) $rule->uuid)]), 'payload' => null,
            'dry_run' => false, 'preview_count' => null, 'max_count' => 0, 'confirmed_above_max' => false, 'status' => 'queued', 'progress' => 0,
            'affected_count' => 0, 'failed_count' => 0, 'created_by' => $user->id, 'correlation_id' => $this->correlation->get(),
        ]);
        DB::table('duplicate_rules')->where('id', $rule->id)->update(['last_sweep_operation_id' => $id]);
        $this->audit->record('duplicate_rules.sweep_started', 'data', null, 'form', (int) $rule->form_id, ['rule' => $rule->uuid], $user->id, null, (int) $rule->form_id);
        RunDuplicateSweep::dispatch($id)->afterCommit();

        return DB::table('bulk_operations')->where('id', $id)->first() ?? throw new \LogicException('operation vanished');
    }

    /** Runs a sweep (from RunDuplicateSweep). */
    public function runSweep(int $operationId): void
    {
        $op = DB::table('bulk_operations')->where('id', $operationId)->first();
        if ($op === null || $op->status !== 'queued') {
            return;
        }
        $now = Carbon::now('UTC');
        DB::table('bulk_operations')->where('id', $op->id)->update(['status' => 'running', 'started_at' => $now->format('Y-m-d H:i:s.u'), 'updated_at' => $now->format('Y-m-d H:i:s.u')]);
        $criteria = json_decode((string) $op->criteria, true) ?: [];
        $rule = DB::table('duplicate_rules')->where('uuid', (string) ($criteria['rule'] ?? ''))->first();
        $form = Form::query()->find($op->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        try {
            if ($rule === null || $rt === null) {
                throw new \RuntimeException((string) __('data_quality.rule_gone'));
            }
            $groups = $this->finder->sweep($rt, self::matcher($rule));
            $uuids = [];
            foreach (array_chunk(array_merge(...($groups ?: [[]])), 1000) as $chunk) {
                foreach (DB::table($rt->table)->whereIn('id', $chunk)->pluck('uuid', 'id') as $id => $u) {
                    $uuids[(int) $id] = strtolower((string) $u);
                }
            }
            $result = array_map(static fn (array $g) => array_values(array_filter(array_map(static fn ($id) => $uuids[$id] ?? null, $g))), array_slice($groups, 0, 1000));
            $finished = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('bulk_operations')->where('id', $op->id)->update([
                'status' => 'completed', 'progress' => 100, 'affected_count' => array_sum(array_map('count', $groups)), 'preview_count' => count($groups),
                'result' => json_encode(['groups' => $result]), 'finished_at' => $finished, 'updated_at' => $finished,
            ]);
            DB::table('duplicate_rules')->where('id', $rule->id)->update(['last_sweep_at' => $finished]);
        } catch (Throwable $e) {
            report($e);
            DB::table('bulk_operations')->where('id', $op->id)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        }
    }

    /**
     * A sweep's state and the groups found, as records the user may see.
     *
     * @return array<string, mixed>
     */
    public function presentSweep(object $op, User $user): array
    {
        $form = Form::query()->find($op->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $result = json_decode((string) ($op->result ?? 'null'), true) ?: [];
        $groups = [];
        $hidden = 0;
        if ($rt !== null && $this->access->allows($user, "form.{$rt->form->uuid}.view")) {
            foreach ((array) ($result['groups'] ?? []) as $g) {
                $rows = DB::table($rt->table)->whereIn('uuid', $g)->whereNull('deleted_at')->get();
                $members = [];
                foreach ($rows as $r) {
                    if ($this->scope->allows($rt, $user, 'view', (int) $r->id)) {
                        $members[] = ['uuid' => strtolower((string) $r->uuid), 'number' => $r->record_number, 'title' => $this->bin->title($rt, $r)];
                    } else {
                        $hidden++;
                    }
                }
                if (count($members) > 1) {
                    $groups[] = $members;
                }
            }
        }

        return [
            'uuid' => strtolower((string) $op->uuid), 'status' => $op->status, 'groups' => $groups, 'hidden' => $hidden, 'error' => $op->error,
            'finished_at' => $op->finished_at === null ? null : Carbon::parse((string) $op->finished_at, 'UTC')->toIso8601ZuluString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function validate(FormRuntime $rt, array $rules): array
    {
        $errors = [];
        $out = [];
        $seen = [];
        $default = $this->translator->defaultLocale();
        if (count($rules) > self::MAX_RULES) {
            $errors['rules'][] = __('data_quality.too_many_rules', ['max' => self::MAX_RULES]);
        }
        foreach (array_values($rules) as $i => $r) {
            $p = "rules.{$i}";
            if (! is_array($r) || ! is_string($r['uuid'] ?? null) || ! Str::isUuid($r['uuid']) || isset($seen[strtolower($r['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $uuid = strtolower($r['uuid']);
            $seen[$uuid] = true;
            if (DB::table('duplicate_rules')->where('uuid', $uuid)->where('form_id', '!=', $rt->form->id)->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $name = $this->texts($r['i18n']['name'] ?? [], 120);
            if (($name[$default] ?? '') === '') {
                $errors["{$p}.i18n.name.{$default}"][] = __('data_quality.name_required');
            }
            $fields = [];
            $raw = array_values((array) ($r['fields'] ?? []));
            if ($raw === []) {
                $errors["{$p}.fields"][] = __('data_quality.fields_required');
            }
            if (count($raw) > self::MAX_FIELDS) {
                $errors["{$p}.fields"][] = __('data_quality.too_many_fields', ['max' => self::MAX_FIELDS]);
            }
            foreach ($raw as $k => $m) {
                $f = is_string($m['field'] ?? null) ? ($rt->fields[strtolower($m['field'])] ?? null) : null;
                $storage = $f === null ? null : $rt->type($f)?->storage;
                if ($f === null || isset($rt->fieldRepeater[$f['uuid']]) || ! in_array($storage, DuplicateFinder::COMPARABLE, true) || $rt->column($f['uuid']) === null) {
                    $errors["{$p}.fields.{$k}.field"][] = __('data_quality.bad_field');

                    continue;
                }
                $method = in_array($m['method'] ?? null, self::METHODS, true) ? (string) $m['method'] : 'exact';
                if ($method !== 'exact' && ! in_array($storage, DuplicateFinder::TEXT_STORAGE, true)) {
                    $errors["{$p}.fields.{$k}.method"][] = __('data_quality.text_only');
                }
                $threshold = null;
                if ($method === 'fuzzy') {
                    $threshold = $m['threshold'] ?? 0.85;
                    if (! is_numeric($threshold) || (float) $threshold < 0.5 || (float) $threshold > 1) {
                        $errors["{$p}.fields.{$k}.threshold"][] = __('data_quality.bad_threshold');
                    }
                    $threshold = round((float) $threshold, 2);
                }
                $fields[] = ['field' => $f['uuid'], 'method' => $method, 'threshold' => $threshold];
            }
            $out[] = [
                'uuid' => $uuid, 'i18n' => ['name' => $name, 'message' => $this->texts($r['i18n']['message'] ?? [], 500)], 'fields' => $fields,
                'mode' => ($r['mode'] ?? 'all') === 'any' ? 'any' : 'all', 'action' => ($r['action'] ?? 'warn') === 'block' ? 'block' : 'warn',
                'appliesOn' => in_array($r['appliesOn'] ?? 'both', ['create', 'update', 'both'], true) ? (string) ($r['appliesOn'] ?? 'both') : 'both',
                'active' => (bool) ($r['active'] ?? true),
            ];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** @return array<string, string> */
    private function texts(mixed $map, int $max): array
    {
        $out = [];
        foreach ((array) $map as $locale => $text) {
            if (is_string($text) && trim($text) !== '' && in_array($locale, $this->translator->enabledLocales(), true)) {
                $out[(string) $locale] = mb_substr($text, 0, $max);
            }
        }

        return $out;
    }
}
