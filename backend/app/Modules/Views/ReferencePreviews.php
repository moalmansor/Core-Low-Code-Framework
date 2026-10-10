<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\DefinitionCompiler;
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
 * Edit Mode reference previews (specification §4.14 "Edit Mode"): when a user
 * picks a record in a lookup, a card shows chosen fields of that record,
 * selected fields of the form being edited can be filled from it, and the
 * record can open in a side drawer. A form defines its default card (used by
 * every lookup that points at it) and overrides for its own lookup fields,
 * which is where auto-fill is configured.
 */
final class ReferencePreviews
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RelationPaths $paths,
        private readonly RecordScope $scope,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array{default: array<string, mixed>|null, fields: list<array<string, mixed>>} */
    public function load(Form $form): array
    {
        $default = DB::table('reference_previews')->where('target_form_id', $form->id)->whereNull('field_id')->first();
        $ownFields = DB::table('fields')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $overrides = DB::table('reference_previews')->whereIn('field_id', array_keys($ownFields) ?: [0])->orderBy('id')->get();

        return [
            'default' => $default === null ? null : $this->present($default),
            'fields' => $overrides->map(fn ($r) => ['field' => strtolower((string) $ownFields[$r->field_id])] + $this->present($r))->values()->all(),
        ];
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @param  array{default?: array<string, mixed>|null, fields?: list<array<string, mixed>>}  $doc */
    public function save(Form $form, array $doc, int $userId): void
    {
        $rt = $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['previews' => __('views.publish_first')]);
        }
        $errors = [];
        $rows = [];
        if (($doc['default'] ?? null) !== null) {
            $errors += $this->check($rt, $doc['default'], 'default', null);
            $rows[] = ['target' => $form->id, 'field' => null, 'doc' => $doc['default']];
        }
        foreach (array_values($doc['fields'] ?? []) as $i => $f) {
            $uuid = is_string($f['field'] ?? null) ? strtolower($f['field']) : '';
            $field = $rt->fields[$uuid] ?? null;
            $target = $field === null ? null : $rt->targetFormUuid($field);
            $trt = $target === null ? null : $this->runtimes->forUuid($target);
            if ($trt === null || isset($rt->fieldRepeater[$uuid])) {
                $errors["fields.{$i}.field"][] = __('panels.unknown_lookup');

                continue;
            }
            $errors += $this->check($trt, $f, "fields.{$i}", $rt);
            $rows[] = ['target' => $trt->form->id, 'field' => (int) DB::table('fields')->where('form_id', $form->id)->where('uuid', $uuid)->value('id'), 'doc' => $f];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($form, $rows, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $ownFieldIds = DB::table('fields')->where('form_id', $form->id)->pluck('id')->all();
            DB::table('reference_previews')->where(static fn ($q) => $q->where(static fn ($w) => $w->where('target_form_id', $form->id)->whereNull('field_id'))->orWhereIn('field_id', $ownFieldIds ?: [0]))->delete();
            foreach ($rows as $r) {
                DB::table('reference_previews')->insert([
                    'uuid' => (string) Str::uuid7(), 'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now,
                    'created_by' => $userId, 'updated_by' => $userId, 'target_form_id' => $r['target'], 'field_id' => $r['field'],
                    'display_paths' => json_encode(array_values($r['doc']['displayPaths'] ?? [])),
                    'layout' => json_encode(['columns' => in_array($r['doc']['layout']['columns'] ?? 1, [1, 2], true) ? ($r['doc']['layout']['columns'] ?? 1) : 1]),
                    'autofill_map' => $r['field'] === null ? null : json_encode(array_values($r['doc']['autofill'] ?? [])),
                    'drawer_enabled' => (bool) ($r['doc']['drawer'] ?? true),
                ]);
            }
            $this->audit->record('reference_previews.saved', 'config', null, 'form', $form->id, ['previews' => count($rows)], $userId);
        });
    }

    /**
     * The card for a picked record of a lookup field, its auto-fill values,
     * and whether it may open in a drawer. Null when the user cannot see it.
     *
     * @return array<string, mixed>|null
     */
    public function card(FormRuntime $rt, array $field, string $recordUuid, User $user): ?array
    {
        $target = $rt->targetFormUuid($field);
        $trt = $target === null ? null : $this->runtimes->forUuid($target);
        if ($trt === null) {
            return null;
        }
        $row = DB::table($trt->table)->where('uuid', strtolower($recordUuid))->whereNull('deleted_at')->first(['id']);
        if ($row === null || ! $this->scope->allows($trt, $user, 'view', (int) $row->id)) {
            return null;
        }
        $fieldId = DB::table('fields')->where('form_id', $rt->form->id)->where('uuid', $field['uuid'])->value('id');
        $config = DB::table('reference_previews')->where('field_id', $fieldId)->first()
            ?? DB::table('reference_previews')->where('target_form_id', $trt->form->id)->whereNull('field_id')->first();
        $record = $this->store->findById($trt, (int) $row->id);
        $presented = $this->presenter->present($trt, $record, [], false);
        $items = [];
        $autofill = [];
        $drawer = true;
        $columns = 1;
        if ($config !== null) {
            foreach (json_decode((string) $config->display_paths, true) ?: [] as $path) {
                $r = $this->paths->resolve($trt, $path, $user);
                if ($r === null) {
                    continue;
                }
                $label = $r['field'] === null ? __('views.system_'.$r['system']) : (app(Translator::class)->pick($r['field']['i18n']['label'] ?? null) ?? $r['field']['key']);
                $items[] = ['label' => $label, 'value' => $this->paths->values($trt, $path, [(int) $row->id], $user)[(int) $row->id] ?? null];
            }
            foreach (json_decode((string) ($config->autofill_map ?? 'null'), true) ?: [] as $a) {
                $from = $a['from'][0] ?? null;
                $to = $rt->fields[$a['to'] ?? ''] ?? null;
                $r = $from === null ? null : $this->paths->resolve($trt, [$from], $user);
                if ($to === null || $r === null || $r['field'] === null) {
                    continue;
                }
                $autofill[] = ['field' => $to['key'], 'value' => $record['values'][$from] ?? null, 'overwrite' => (bool) ($a['overwrite'] ?? false)];
            }
            $drawer = (bool) $config->drawer_enabled;
            $columns = (int) ((json_decode((string) $config->layout, true) ?: [])['columns'] ?? 1);
        }

        return [
            'form' => ['uuid' => $trt->form->uuid, 'key' => $trt->form->key, 'name' => $trt->form->translate('name') ?? $trt->form->key],
            'record' => ['uuid' => $record['uuid'], 'title' => $presented['title'], 'status' => $presented['system']['status']],
            'items' => $items, 'columns' => $columns, 'autofill' => $autofill, 'drawer' => $drawer,
        ];
    }

    /** @return array<string, mixed> */
    private function present(object $r): array
    {
        return [
            'displayPaths' => json_decode((string) $r->display_paths, true) ?: [],
            'layout' => json_decode((string) $r->layout, true) ?: ['columns' => 1],
            'autofill' => json_decode((string) ($r->autofill_map ?? 'null'), true) ?: [],
            'drawer' => (bool) $r->drawer_enabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, list<string>>
     */
    private function check(FormRuntime $target, array $doc, string $p, ?FormRuntime $source): array
    {
        $errors = [];
        $paths = (array) ($doc['displayPaths'] ?? []);
        if (count($paths) > 12) {
            $errors["{$p}.displayPaths"][] = __('views.too_many');
        }
        foreach ($paths as $j => $path) {
            if (! is_array($path) || $this->paths->resolve($target, $path) === null) {
                $errors["{$p}.displayPaths.{$j}"][] = __('views.unknown_path');
            }
        }
        foreach ((array) ($doc['autofill'] ?? []) as $j => $a) {
            $from = is_array($a['from'] ?? null) && count($a['from']) === 1 ? ($target->fields[$target->keys[$a['from'][0]] ?? ''] ?? null) : null;
            $to = $source === null ? null : ($source->fields[strtolower((string) ($a['to'] ?? ''))] ?? null);
            if ($source === null || $from === null || $to === null || isset($source->fieldRepeater[$to['uuid']])
                || $source->type($to)?->storage !== $target->type($from)?->storage || $source->isMultiReference($to) !== $target->isMultiReference($from)
                || $source->targetFormUuid($to) !== $target->targetFormUuid($from)) {
                $errors["{$p}.autofill.{$j}"][] = __('panels.autofill_incompatible');
            }
        }

        return $errors;
    }
}
