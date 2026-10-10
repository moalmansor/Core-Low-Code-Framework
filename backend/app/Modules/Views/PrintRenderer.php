<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The print view and its PDF (specification §4.14 "Print view"): a record
 * rendered with a print layout into HTML in the user's language and
 * direction, honouring field access in print mode for the record's status;
 * the same HTML becomes a PDF with mPDF, which shapes Arabic and lays out
 * right-to-left text.
 */
final class PrintRenderer
{
    /** @var list<array<string, mixed>>|null */
    private ?array $panelCache = null;

    public function __construct(
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordPresenter $presenter,
        private readonly ViewPanels $panels,
        private readonly Translator $translator,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}  $record
     * @param  array<string, mixed>|null  $layout  a print layout (null: the whole form body)
     */
    public function html(FormRuntime $rt, array $record, User $user, ?array $layout): string
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'print', $record['system']['status_id'] ?? null);
        $presented = $this->presenter->present($rt, $record, $levels['fields']);
        $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);
        $sections = $layout['layout']['sections'] ?? [['type' => 'form_body']];
        $this->panelCache = null;
        $body = '';
        foreach ($sections as $s) {
            $body .= match ($s['type']) {
                'form_body' => $this->formBody($rt, $presented, $levels['fields']),
                'fields' => $this->fieldTable($rt, array_values(array_filter(array_map(static fn ($k) => $rt->fields[$rt->keys[$k] ?? ''] ?? null, $s['fields'] ?? []))), $presented, $levels['fields']),
                'panel' => $this->panel($rt, $record, $user, (string) ($s['panel'] ?? '')),
                'status_history' => $this->history($rt, $record),
                'page_break' => '<pagebreak /><div class="page-break"></div>',
                default => '',
            };
        }
        $title = e($rt->form->translate('name') ?? $rt->form->key).($presented['title'] !== null ? ' — '.e((string) $presented['title']) : '');
        $logo = ($layout['showLogo'] ?? true) ? $this->logo() : null;
        $header = $layout === null ? null : $this->pick((array) ($layout['i18n']['header'] ?? []));
        $footer = $layout === null ? null : $this->pick((array) ($layout['i18n']['footer'] ?? []));
        $status = $presented['system']['status'] ?? null;
        $meta = '<table class="meta"><tr>'
            .($presented['system']['record_number'] !== null ? '<td><b>'.e(__('views.system_record_number')).':</b> '.e((string) $presented['system']['record_number']).'</td>' : '')
            .($status !== null ? '<td><b>'.e(__('views.system_status')).':</b> '.e((string) $status['name']).'</td>' : '')
            .'<td><b>'.e(__('views.system_updated_at')).':</b> '.e($this->date($presented['system']['updated_at'])).'</td></tr></table>';

        return '<!doctype html><html lang="'.e(app()->getLocale()).'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><title>'.$title.'</title><style>'.$this->css($rtl).'</style></head><body>'
            .'<header>'.($logo !== null ? '<img class="logo" src="'.$logo.'" alt="">' : '').'<h1>'.$title.'</h1>'.($header !== null ? '<p class="note">'.nl2br(e($header)).'</p>' : '').'</header>'
            .$meta.$body
            .($footer !== null ? '<footer><p class="note">'.nl2br(e($footer)).'</p></footer>' : '')
            .'<p class="printed">'.e(__('panels.printed_at', ['at' => $this->date(Carbon::now('UTC')->toIso8601ZuluString()), 'by' => $user->name])).'</p>'
            .'</body></html>';
    }

    /** @param  array<string, mixed>|null  $layout */
    public function pdf(string $html, ?array $layout): string
    {
        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $format = strtoupper((string) ($layout['paper'] ?? 'a4'));
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format === 'LETTER' ? 'Letter' : ($format === 'LEGAL' ? 'Legal' : $format),
            'orientation' => ($layout['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P',
            'tempDir' => $dir,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoArabic' => true,
        ]);
        $mpdf->SetDirectionality(in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr');
        // Remote resources are never fetched while rendering (all images are embedded).
        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @param  array<string, mixed>  $presented
     * @param  array<string, string>  $levels
     */
    private function formBody(FormRuntime $rt, array $presented, array $levels): string
    {
        $out = '';
        $byGroup = [];
        foreach ($rt->mainFields() as $f) {
            $byGroup[$f['group'] ?? ''][] = $f;
        }
        $groups = array_values($rt->groups);
        usort($groups, static fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        $out .= $this->fieldTable($rt, $byGroup[''] ?? [], $presented, $levels);
        foreach ($groups as $g) {
            if (($levels[$g['uuid']] ?? null) === 'hidden') {
                continue;
            }
            if ($g['type'] === 'repeater') {
                $out .= $this->repeater($rt, $g, $presented, $levels);

                continue;
            }
            $table = $this->fieldTable($rt, $byGroup[$g['uuid']] ?? [], $presented, $levels);
            if ($table !== '') {
                $out .= '<h2>'.e($this->translator->labelOf($g['i18n']['title'] ?? null, $g['key'])).'</h2>'.$table;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $presented
     * @param  array<string, string>  $levels
     */
    private function fieldTable(FormRuntime $rt, array $fields, array $presented, array $levels): string
    {
        $rows = '';
        foreach ($fields as $f) {
            if (($levels[$f['uuid']] ?? 'hidden') === 'hidden' || ! $rt->isStored($f) || isset($rt->fieldRepeater[$f['uuid']])) {
                continue;
            }
            $rows .= '<tr><th>'.e($this->translator->labelOf($f['i18n']['label'] ?? null, $f['key'])).'</th><td>'.$this->value($rt, $f, $presented['values'][$f['key']] ?? null, $presented).'</td></tr>';
        }

        return $rows === '' ? '' : '<table class="fields">'.$rows.'</table>';
    }

    /**
     * @param  array<string, mixed>  $g
     * @param  array<string, mixed>  $presented
     * @param  array<string, string>  $levels
     */
    private function repeater(FormRuntime $rt, array $g, array $presented, array $levels): string
    {
        $fields = array_values(array_filter($rt->rowFields($g['uuid']), static fn ($f) => ($levels[$f['uuid']] ?? 'hidden') !== 'hidden'));
        $rows = $presented['values'][$g['key']] ?? [];
        if ($fields === []) {
            return '';
        }
        $head = implode('', array_map(fn ($f) => '<th>'.e($this->translator->labelOf($f['i18n']['label'] ?? null, $f['key'])).'</th>', $fields));
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>'.implode('', array_map(fn ($f) => '<td>'.$this->value($rt, $f, $row[$f['key']] ?? null, $presented).'</td>', $fields)).'</tr>';
        }

        return '<h2>'.e($this->translator->labelOf($g['i18n']['title'] ?? null, $g['key'])).'</h2><table class="grid"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    /** @param  array<string, mixed>  $record */
    private function panel(FormRuntime $rt, array $record, User $user, string $uuid): string
    {
        // The record's panels are rendered once per print.
        $this->panelCache ??= $this->panels->render($rt, $record, $user);
        foreach ($this->panelCache as $p) {
            if ($p['uuid'] !== strtolower($uuid)) {
                continue;
            }
            $title = $p['title'] !== null ? '<h2>'.e($p['title']).'</h2>' : '';

            return match ($p['type']) {
                'derived_fields' => $title.'<table class="fields">'.implode('', array_map(fn ($i) => '<tr><th>'.e($i['label']).'</th><td>'.e($this->scalar($i['value'])).'</td></tr>', $p['data'] ?? [])).'</table>',
                'summary_widget' => $title.'<p class="widget">'.e($this->scalar($p['data']['value'] ?? null)).'</p>',
                'related_table' => $title.$this->related($p['data']),
                'status_timeline' => $title.$this->history($rt, $record),
                'html' => $title.($p['content'] ?? ''),
                default => '',
            };
        }

        return '';
    }

    /** @param  array<string, mixed>|null  $data */
    private function related(?array $data): string
    {
        if ($data === null) {
            return '';
        }
        $head = '<th>#</th>'.implode('', array_map(static fn ($c) => '<th>'.e($c['label']).'</th>', $data['columns']));
        $body = '';
        foreach ($data['rows'] as $r) {
            $body .= '<tr><td>'.e((string) ($r['title'] ?? '')).'</td>'.implode('', array_map(fn ($c) => '<td>'.e($this->scalar(((array) $r['cells'])[$c['key']] ?? null)).'</td>', $data['columns'])).'</tr>';
        }

        return '<table class="grid"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }

    /** @param  array<string, mixed>  $record */
    private function history(FormRuntime $rt, array $record): string
    {
        $wf = WorkflowRuntime::for($rt);
        $rows = DB::table('status_history')->where('form_id', $rt->form->id)->where('record_id', $record['id'])->orderBy('acted_at')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return '';
        }
        $users = DB::table('users')->whereIn('id', $rows->pluck('acted_by')->filter())->pluck('name', 'id');
        $name = static function (?int $id) use ($wf): string {
            $s = $wf->status($id);
            if ($s !== null) {
                return WorkflowRuntime::label($s);
            }

            return $id === null ? '—' : (string) (app(Translator::class)->get('status', $id, 'name') ?? DB::table('statuses')->where('id', $id)->value('key'));
        };
        $body = '';
        foreach ($rows as $h) {
            $body .= '<tr><td>'.e($this->date(Carbon::parse($h->acted_at, 'UTC')->toIso8601ZuluString())).'</td><td>'.e($name($h->from_status_id === null ? null : (int) $h->from_status_id)).' → '.e($name((int) $h->to_status_id)).'</td><td>'.e((string) ($users[$h->acted_by] ?? '')).'</td><td>'.nl2br(e((string) ($h->comment ?? ''))).'</td></tr>';
        }

        return '<h2>'.e(__('panels.status_history')).'</h2><table class="grid"><tbody>'.$body.'</tbody></table>';
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $presented
     */
    private function value(FormRuntime $rt, array $f, mixed $value, array $presented): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        $refs = (array) (((array) $presented['references'])[$f['key']] ?? []);
        if ($refs !== []) {
            return e(implode(', ', array_map(static fn ($u) => $refs[$u] ?? $u, (array) $value)));
        }
        $files = (array) $presented['files'];
        if (in_array($rt->type($f)?->storage, ['file', 'files'], true)) {
            return e(implode(', ', array_map(static fn ($u) => $files[$u]['name'] ?? $u, (array) $value)));
        }
        foreach ($f['options']['static'] ?? [] as $o) {
            $labels[$o['value']] = $this->pick((array) ($o['i18n']['label'] ?? [])) ?? $o['value'];
        }
        if (isset($labels)) {
            return e(implode(', ', array_map(static fn ($v) => $labels[$v] ?? (string) $v, (array) $value)));
        }
        if (is_bool($value)) {
            return e($value ? __('panels.yes') : __('panels.no'));
        }
        if ($f['type'] === 'richtext' && is_string($value)) {
            return $value; // sanitized when saved
        }

        return nl2br(e($this->scalar($value)));
    }

    private function scalar(mixed $v): string
    {
        return match (true) {
            $v === null => '—',
            is_bool($v) => $v ? __('panels.yes') : __('panels.no'),
            is_array($v) => implode(', ', array_map(fn ($x) => $this->scalar($x), array_values($v))),
            default => (string) $v,
        };
    }

    private function logo(): ?string
    {
        $uuid = $this->settings->get('branding', 'logo_file');
        $file = is_string($uuid) ? StoredFile::query()->where('uuid', $uuid)->first() : null;
        if ($file === null || ! in_array($file->mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            return null;
        }
        $data = Storage::disk($file->disk)->get($file->path);

        return $data === null ? null : 'data:'.$file->mime_type.';base64,'.base64_encode($data);
    }

    private function date(?string $iso): string
    {
        return $iso === null ? '' : Carbon::parse($iso)->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i');
    }

    /** @param  array<string, string|null>  $values */
    private function pick(array $values): ?string
    {
        return $this->translator->pick($values);
    }

    private function css(bool $rtl): string
    {
        $align = $rtl ? 'right' : 'left';

        return "body{font-family:dejavusans,'Noto Naskh Arabic','Noto Sans',sans-serif;font-size:11pt;color:#111;margin:24px}"
            .'h1{font-size:16pt;margin:0 0 6px}h2{font-size:12.5pt;margin:18px 0 6px;border-bottom:1px solid #999;padding-bottom:3px}'
            .'.logo{max-height:56px;margin-bottom:8px}.note{color:#333;font-size:10pt}.printed{color:#555;font-size:8.5pt;margin-top:24px}'
            ."table{width:100%;border-collapse:collapse;margin:4px 0}th,td{text-align:{$align};vertical-align:top;padding:4px 6px;border:1px solid #bbb}"
            .'.fields th{width:32%;background:#f2f2f2;font-weight:bold}.grid thead th{background:#f2f2f2}.meta td{border:none;padding:2px 6px 8px 0}'
            .'.widget{font-size:18pt;font-weight:bold}.page-break{page-break-before:always}'
            .'td{unicode-bidi:plaintext}@media print{body{margin:0}}';
    }
}
