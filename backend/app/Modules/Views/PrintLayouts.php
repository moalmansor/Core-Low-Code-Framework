<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Print layouts of a form (specification §4.14 "Print view"): paper and
 * orientation, logo, header and footer text in each language, and the
 * sections printed in order — the whole form body, chosen fields, View Mode
 * panels, the status history, page breaks.
 */
final class PrintLayouts
{
    public const PAPERS = ['a4', 'a3', 'letter', 'legal'];

    public const SECTIONS = ['form_body', 'fields', 'panel', 'status_history', 'page_break'];

    public function __construct(private readonly Translator $translator, private readonly FormRuntimes $runtimes, private readonly AuditWriter $audit) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('print_layouts')->where('form_id', $form->id)->orderBy('id')->get();
        $tr = $this->translator->allMany('print_layout', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());

        return $rows->map(static fn ($r) => [
            'uuid' => strtolower((string) $r->uuid), 'key' => $r->key,
            'i18n' => ['name' => (object) ($tr[(int) $r->id]['name'] ?? []), 'header' => (object) ($tr[(int) $r->id]['header'] ?? []), 'footer' => (object) ($tr[(int) $r->id]['footer'] ?? [])],
            'paper' => $r->paper, 'orientation' => $r->orientation, 'layout' => json_decode((string) $r->layout, true) ?: ['sections' => []],
            'showLogo' => (bool) $r->show_logo, 'default' => (bool) $r->is_default,
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @param  list<array<string, mixed>>  $layouts */
    public function save(Form $form, array $layouts, int $userId): void
    {
        $rt = $this->runtimes->forForm($form);
        $errors = [];
        $keys = [];
        $defaults = 0;
        $panels = DB::table('view_panels')->where('form_id', $form->id)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        foreach ($layouts as $i => &$l) {
            $p = "layouts.{$i}";
            if (! is_array($l) || ! is_string($l['uuid'] ?? null) || ! Str::isUuid($l['uuid'])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $l['uuid'] = strtolower($l['uuid']);
            if (! is_string($l['key'] ?? null) || preg_match('/^[a-z][a-z0-9_]{0,47}$/', $l['key']) !== 1 || isset($keys[$l['key']])) {
                $errors["{$p}.key"][] = __('views.invalid_key');
            }
            $keys[$l['key'] ?? ''] = true;
            $defaults += ($l['default'] ?? false) ? 1 : 0;
            if (! in_array($l['paper'] ?? 'a4', self::PAPERS, true) || ! in_array($l['orientation'] ?? 'portrait', ['portrait', 'landscape'], true)) {
                $errors["{$p}.paper"][] = __('validation.in', ['attribute' => 'paper']);
            }
            if (trim((string) (((array) ($l['i18n']['name'] ?? []))[$this->translator->defaultLocale()] ?? '')) === '') {
                $errors["{$p}.i18n.name"][] = __('workflow.name_required');
            }
            $sections = array_values((array) ($l['layout']['sections'] ?? []));
            if ($sections === [] || count($sections) > 50) {
                $errors["{$p}.layout.sections"][] = __('panels.sections_required');
            }
            foreach ($sections as $j => $s) {
                $type = $s['type'] ?? null;
                if (! in_array($type, self::SECTIONS, true)) {
                    $errors["{$p}.layout.sections.{$j}.type"][] = __('validation.in', ['attribute' => 'type']);
                } elseif ($type === 'fields' && ($rt === null || array_diff((array) ($s['fields'] ?? []), array_keys($rt->keys)) !== [] || ($s['fields'] ?? []) === [])) {
                    $errors["{$p}.layout.sections.{$j}.fields"][] = __('views.unknown_path');
                } elseif ($type === 'panel' && ! in_array(strtolower((string) ($s['panel'] ?? '')), $panels, true)) {
                    $errors["{$p}.layout.sections.{$j}.panel"][] = __('panels.unknown_panel');
                }
            }
            $l['layout'] = ['sections' => $sections];
        }
        unset($l);
        if ($layouts !== [] && $defaults !== 1) {
            $errors['layouts'][] = __('panels.one_default_layout');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($form, $layouts, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            $tr = [];
            foreach ($layouts as $l) {
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'key' => $l['key'], 'paper' => $l['paper'] ?? 'a4', 'orientation' => $l['orientation'] ?? 'portrait',
                    'layout' => json_encode($l['layout']), 'show_logo' => (bool) ($l['showLogo'] ?? true), 'is_default' => (bool) ($l['default'] ?? false),
                ];
                $id = DB::table('print_layouts')->where('form_id', $form->id)->where('uuid', $l['uuid'])->value('id');
                if ($id === null) {
                    $id = DB::table('print_layouts')->insertGetId($row + ['uuid' => $l['uuid'], 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId, 'form_id' => $form->id]);
                } else {
                    DB::table('print_layouts')->where('id', $id)->update($row);
                }
                $tr[(int) $id] = ['name' => (array) ($l['i18n']['name'] ?? []), 'header' => (array) ($l['i18n']['header'] ?? []), 'footer' => (array) ($l['i18n']['footer'] ?? [])];
                $kept[] = (int) $id;
            }
            $this->translator->syncObjects('print_layout', $tr);
            foreach (DB::table('print_layouts')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->pluck('id') as $gone) {
                $this->translator->forget('print_layout', (int) $gone);
            }
            DB::table('print_layouts')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->delete();
            $this->audit->record('print_layouts.saved', 'config', null, 'form', $form->id, ['layouts' => count($layouts)], $userId);
        });
    }
}
