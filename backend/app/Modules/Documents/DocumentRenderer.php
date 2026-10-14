<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Modules\Core\I18n\Translator;
use App\Modules\Documents\Docx\DocxBuilder;
use App\Modules\Documents\Docx\DocxPackage;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Templating\RenderContext;
use App\Modules\Records\Templating\TemplateValues;
use App\Modules\Views\PrintRenderer;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use Throwable;

/**
 * Generates a document from a template for a record (specification §4.17,
 * architecture §19.14) in one language, for the people who will read it: a
 * value appears only when every one of them may see it (TemplateValues), so
 * the same file can go to several email recipients. A Word template is
 * filled as uploaded; a designed template becomes a Word file built from its
 * blocks, or a PDF laid out by mPDF (which shapes Arabic and lays out
 * right-to-left text). PDF from a Word template goes through LibreOffice.
 */
final class DocumentRenderer
{
    public const RTL = ['ar', 'fa', 'he', 'ur'];

    public const MIME = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pdf' => 'application/pdf'];

    public function __construct(
        private readonly TemplateValues $values,
        private readonly Translator $translator,
        private readonly DocxBuilder $builder,
        private readonly PdfConverter $converter,
        private readonly CodeImages $codes,
    ) {}

    /**
     * @param  list<User>  $viewers  whose field access applies
     * @return array{bytes: string, name: string, extension: string, mime: string}
     */
    public function render(DocumentTemplate $t, ?FormRuntime $rt, ?int $recordId, array $viewers, string $locale, string $format, ?User $actor = null): array
    {
        if (! in_array($format, ['docx', 'pdf'], true)) {
            throw new RuntimeException("Unknown document format {$format}.");
        }
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            $ctx = $this->values->context($rt, $recordId, $viewers, $locale, count($viewers) === 1 ? $viewers[0]->name : null, $actor?->name);
            $name = $this->name($t, $ctx);
            if ($t->type === 'docx') {
                $docx = $this->fillWord($t, $ctx);
                $bytes = $format === 'pdf' ? $this->converter->convert($docx) : $docx;
            } else {
                $design = (array) $t->design;
                $blocks = $this->resolve($design, $ctx);
                $page = $this->page($design, $ctx, $name);
                $bytes = $format === 'pdf'
                    ? $this->pdf($this->html($blocks, $page, $t->paper, $locale, $name, $this->values->accent($design)), $page, $t->paper, $locale, $name)
                    : $this->builder->build($blocks, $t->paper, in_array($locale, self::RTL, true), $name, $this->values->accent($design), $page);
            }

            return ['bytes' => $bytes, 'name' => $name, 'extension' => $format, 'mime' => self::MIME[$format]];
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * A designed template as an HTML page, for the designer's preview.
     *
     * @param  array<string, mixed>  $design
     * @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper
     * @param  list<User>  $viewers
     */
    public function preview(array $design, array $paper, ?FormRuntime $rt, ?int $recordId, array $viewers, string $locale, string $title): string
    {
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            $ctx = $this->values->context($rt, $recordId, $viewers, $locale, $viewers[0]->name ?? null, $viewers[0]->name ?? null);

            return $this->html($this->resolve($design, $ctx), $this->page($design, $ctx, $title), $paper, $locale, $title, $this->values->accent($design), true);
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * The design's blocks with their values, as the readers see them.
     *
     * @param  array<string, mixed>  $design
     * @return list<array{type: string, runs?: list<array{text: string, bold: bool}>, paragraphs?: list<list<array{text: string, bold: bool}>>, rows?: list<array{label: string, value: string}>, head?: list<string>, cells?: list<list<string>>}>
     */
    public function resolve(array $design, RenderContext $ctx): array
    {
        $out = [];
        foreach ((array) ($design['blocks'] ?? []) as $b) {
            if (! is_array($b) || ! $this->values->shown($b, $ctx)) {
                continue;
            }
            $text = (string) ($this->translator->pick((array) ($b['text'] ?? []), $ctx->locale) ?? '');
            switch ($b['type'] ?? '') {
                case 'heading':
                    $out[] = ['type' => 'heading', 'runs' => $this->values->runs($text, $ctx)];
                    break;
                case 'text':
                    $paras = [];
                    foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $para) {
                        if (trim($para) !== '') {
                            $paras[] = $this->values->runs($para, $ctx);
                        }
                    }
                    $out[] = ['type' => 'text', 'paragraphs' => $paras];
                    break;
                case 'fields':
                    $out[] = ['type' => 'fields', 'rows' => $this->values->fieldRows(array_values(array_map('strval', (array) ($b['fields'] ?? []))), $ctx)];
                    break;
                case 'table':
                    $table = $this->values->table((string) ($b['group'] ?? ''), array_values(array_map('strval', (array) ($b['columns'] ?? []))), $ctx, (array) ($b['formats'] ?? []));
                    if ($table !== null) {
                        $out[] = ['type' => 'table', 'head' => $table['head'], 'cells' => $table['rows']];
                    }
                    break;
                case 'related':
                    $table = $this->values->related((string) ($b['source'] ?? ''), (string) ($b['via'] ?? ''), array_values(array_map('strval', (array) ($b['columns'] ?? []))), (int) ($b['limit'] ?? 50), $ctx);
                    if ($table !== null) {
                        $out[] = ['type' => 'table', 'head' => $table['head'], 'cells' => $table['rows']];
                    }
                    break;
                case 'signature':
                    $people = [];
                    foreach ((array) ($b['signatories'] ?? []) as $person) {
                        $people[] = [
                            'label' => $this->values->plain((string) ($this->translator->pick((array) ($person['label'] ?? []), $ctx->locale) ?? ''), $ctx),
                            'name' => $this->values->plain((string) ($this->translator->pick((array) ($person['name'] ?? []), $ctx->locale) ?? ''), $ctx),
                        ];
                    }
                    $out[] = ['type' => 'signature', 'people' => $people, 'date' => (bool) ($b['date'] ?? true)];
                    break;
                case 'code':
                    $value = ($b['content'] ?? 'link') === 'number' ? $this->values->value('@record_number', $ctx) : $this->values->value('@link', $ctx);
                    if ($value === '' || $ctx->recordId === null) {
                        break;
                    }
                    try {
                        $image = $this->codes->draw((string) ($b['kind'] ?? 'qr'), $value, (string) ($b['size'] ?? 'medium'));
                    } catch (Throwable) {
                        // A value a barcode cannot hold (letters outside its set) is shown as text instead.
                        $image = null;
                    }
                    $out[] = ['type' => 'code', 'image' => $image, 'caption' => ($b['caption'] ?? true) || $image === null ? $value : ''];
                    break;
                case 'divider':
                case 'spacer':
                case 'page_break':
                    $out[] = ['type' => (string) $b['type']];
                    break;
            }
        }

        // A page break with nothing after it (the rest hidden by conditions) would only add a blank page.
        while ($out !== [] && $out[array_key_last($out)]['type'] === 'page_break') {
            array_pop($out);
        }

        return $out;
    }

    /**
     * What surrounds the content: the document's title, the running header
     * (the organization's logo and a text) and footer (a text and the page
     * number), filled for this rendering.
     *
     * @param  array<string, mixed>  $design
     * @return array{title: string, header: array{logo: array{mime: string, bytes: string}|null, text: string}|null, footer: array{text: string, numbers: string}|null}
     */
    public function page(array $design, RenderContext $ctx, string $name): array
    {
        $text = fn (mixed $map) => $this->values->plain((string) ($this->translator->pick((array) $map, $ctx->locale) ?? ''), $ctx);
        $header = (array) ($design['header'] ?? []);
        $footer = (array) ($design['footer'] ?? []);

        return [
            'title' => $text($design['title'] ?? []),
            'header' => ($header['enabled'] ?? false) ? ['logo' => ($header['logo'] ?? true) ? app(PrintRenderer::class)->logoData() : null, 'text' => $text($header['text'] ?? [])] : null,
            'footer' => ($footer['enabled'] ?? false) ? ['text' => $text($footer['text'] ?? []), 'numbers' => (string) ($footer['pageNumbers'] ?? 'none')] : null,
        ];
    }

    /** The file name: the template's name, and the record number when there is one. */
    private function name(DocumentTemplate $t, RenderContext $ctx): string
    {
        $name = $this->translator->pick($t->translationsFor('name'), $ctx->locale) ?? Translator::humanize($t->key);
        $number = $ctx->recordId === null ? '' : $this->values->value('@record_number', $ctx);

        return trim($name.($number !== '' ? ' '.$number : ''));
    }

    private function fillWord(DocumentTemplate $t, RenderContext $ctx): string
    {
        $files = (array) $t->locale_files;
        $uuid = $files[$ctx->locale] ?? $files[$this->translator->defaultLocale()] ?? null;
        $file = $uuid === null ? null : StoredFile::query()->where('uuid', $uuid)->whereNull('deleted_at')->first();
        if ($file === null) {
            throw new RuntimeException('The Word file of this template is missing.');
        }
        $path = tempnam(sys_get_temp_dir(), 'lcf-tpl-');
        if ($path === false) {
            throw new RuntimeException('Could not prepare the document.');
        }
        try {
            file_put_contents($path, (string) Storage::disk($file->disk)->get($file->path));

            return DocxPackage::fill(
                $path,
                fn (string $token, string $spec) => $this->values->formatted($token, $spec, $ctx),
                function (string $group, array $columns) use ($ctx): ?array {
                    if ($this->values->repeater($ctx->rt, $group) === null) {
                        return null;
                    }
                    // One table per format the row asks for, so a column may appear plain and formatted.
                    $bySpec = [];
                    foreach ($columns as $entry) {
                        [$key, $spec] = array_pad(explode('|', $entry, 2), 2, '');
                        $bySpec[$spec][$key] = $entry;
                    }
                    $rows = null;
                    foreach ($bySpec as $spec => $entries) {
                        $keys = array_keys($entries);
                        $table = $this->values->table($group, $keys, $ctx, array_fill_keys($keys, $spec));
                        if ($table === null) {
                            continue;
                        }
                        foreach ($table['rows'] as $i => $cells) {
                            foreach ($table['keys'] as $c => $key) {
                                $rows[$i][$entries[$key]] = $cells[$c];
                            }
                        }
                    }

                    return $rows === null ? [] : array_values($rows);
                },
            );
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array{title: string, header: array{logo: array{mime: string, bytes: string}|null, text: string}|null, footer: array{text: string, numbers: string}|null}  $page
     * @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper
     */
    private function html(array $blocks, array $page, array $paper, string $locale, string $title, string $accent, bool $screen = false): string
    {
        $rtl = in_array($locale, self::RTL, true);
        $start = $rtl ? 'right' : 'left';
        $runs = static fn (array $rs): string => implode('', array_map(static fn (array $r) => $r['bold'] ? '<b>'.nl2br(e($r['text'])).'</b>' : nl2br(e($r['text'])), $rs));
        $img = static fn (array $i, int $w, int $h): string => '<img src="data:'.$i['mime'].';base64,'.base64_encode($i['bytes']).'" width="'.$w.'" height="'.$h.'" alt="">';
        $body = $page['title'] !== '' ? '<h1 class="doc-title">'.e($page['title']).'</h1>' : '';
        foreach ($blocks as $b) {
            $body .= match ($b['type']) {
                'heading' => '<h2>'.$runs($b['runs'] ?? []).'</h2>',
                'text' => implode('', array_map(static fn (array $p) => '<p>'.$runs($p).'</p>', $b['paragraphs'] ?? [])),
                'fields' => ($b['rows'] ?? []) === [] ? '' : '<table class="fields">'.implode('', array_map(static fn (array $r) => '<tr><th>'.e($r['label']).'</th><td>'.($r['value'] === '' ? '<span class="empty">—</span>' : nl2br(e($r['value']))).'</td></tr>', $b['rows'])).'</table>',
                'table' => '<table class="grid"><thead><tr>'.implode('', array_map(static fn (string $h) => '<th>'.e($h).'</th>', $b['head'] ?? [])).'</tr></thead><tbody>'
                    .implode('', array_map(static fn (array $row) => '<tr>'.implode('', array_map(static fn (string $c) => '<td>'.nl2br(e($c)).'</td>', $row)).'</tr>', $b['cells'] ?? [])).'</tbody></table>',
                'signature' => '<table class="sign"><tr>'.implode('', array_map(static fn (array $p) => '<td><div class="sign-label">'.e($p['label']).'</div><div class="sign-line"></div>'
                    .($p['name'] !== '' ? '<div class="sign-name">'.e($p['name']).'</div>' : '').(($b['date'] ?? false) ? '<div class="sign-date">'.e(__('documents.signature_date')).' ____________</div>' : '').'</td>', $b['people'] ?? [])).'</tr></table>',
                'code' => '<div class="code">'.($b['image'] !== null ? $img(['mime' => 'image/png', 'bytes' => $b['image']['png']], $b['image']['width'], $b['image']['height']) : '')
                    .($b['caption'] !== '' ? '<div class="code-caption" dir="ltr">'.e($b['caption']).'</div>' : '').'</div>',
                'divider' => '<hr>',
                'spacer' => '<div class="spacer"></div>',
                'page_break' => '<pagebreak /><div class="page-break"></div>',
                default => '',
            };
        }
        [$w, $h] = DocxBuilder::PAPER[$paper['size']] ?? DocxBuilder::PAPER['a4'];
        if ($paper['orientation'] === 'landscape') {
            [$w, $h] = [$h, $w];
        }
        $m = $paper['margins'];
        $css = 'body{font-family:dejavusans,"Segoe UI",Tahoma,Arial,sans-serif;font-size:11pt;color:#1f2937;line-height:1.5}'
            .'h1.doc-title{font-size:20pt;color:'.$accent.';margin:0 0 12pt}h2{font-size:15pt;color:'.$accent.';margin:0 0 8pt}p{margin:0 0 8pt}hr{border:0;border-top:1px solid #d1d5db;margin:10pt 0}.spacer{height:12pt}'
            .'table{border-collapse:collapse;margin:4pt 0 10pt}table.fields th{text-align:'.$start.';color:#6b7280;font-weight:normal;padding:3pt '.($rtl ? '0 3pt 16pt' : '16pt 3pt 0').';vertical-align:top;white-space:nowrap}'
            .'table.fields td{padding:3pt 0}.empty{color:#9ca3af}table.grid{width:100%}table.grid th{background:#f3f4f6;font-weight:bold;text-align:'.$start.'}'
            .'table.grid th,table.grid td{border:1px solid #e5e7eb;padding:4pt 6pt;vertical-align:top}'
            .'table.sign{width:100%;margin-top:24pt}table.sign td{padding:0 12pt 0 0;vertical-align:bottom;text-align:'.$start.'}.sign-label{color:#6b7280;font-size:9.5pt}.sign-line{border-bottom:1px solid #1f2937;height:36pt;margin:0 0 4pt}'
            .'.sign-name{font-weight:bold}.sign-date{color:#6b7280;font-size:9.5pt;margin-top:6pt}.code{margin:8pt 0;text-align:'.$start.'}.code-caption{font-size:9pt;color:#374151;margin-top:2pt}'
            .'.run-head{display:flex;align-items:center;gap:12pt;border-bottom:1px solid #e5e7eb;padding-bottom:6pt;margin-bottom:12pt;color:#374151;font-size:9.5pt}.run-head img{max-height:40px}'
            .'.run-foot{border-top:1px solid #e5e7eb;padding-top:6pt;margin-top:16pt;color:#6b7280;font-size:9pt;display:flex;justify-content:space-between;gap:12pt}';
        $header = $page['header'] === null ? '' : '<div class="run-head">'.($page['header']['logo'] !== null ? $img($page['header']['logo'], 0, 0) : '').'<span>'.e($page['header']['text']).'</span></div>';
        $footer = $page['footer'] === null ? '' : '<div class="run-foot"><span>'.e($page['footer']['text']).'</span>'.($page['footer']['numbers'] !== 'none' ? '<span>'.e($this->pageNumber($page['footer']['numbers'], '1', '1')).'</span>' : '').'</div>';
        if ($screen) {
            $css .= 'html{background:#e5e7eb}body{background:#fff;width:'.$w.'mm;min-height:'.$h.'mm;box-sizing:border-box;margin:16px auto;padding:'.$m['top'].'mm '.$m['right'].'mm '.$m['bottom'].'mm '.$m['left'].'mm;box-shadow:0 1px 4px rgba(0,0,0,.15)}'
                .'.page-break{border-top:2px dashed #9ca3af;margin:16px 0}';
            // The screen shows the running header and footer once, around the content; the PDF repeats them on every page.
            $body = $header.$body.$footer;
        }
        $body = str_replace(' width="0" height="0"', '', $body);

        return '<!doctype html><html lang="'.e($locale).'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    /** "Page 2" or "Page 2 of 5", with the page and total given as written (numbers, or the PDF engine's own markers). */
    public function pageNumber(string $style, string $page, string $total): string
    {
        return $style === 'page_of' ? __('documents.page_of', ['page' => $page, 'total' => $total]) : __('documents.page', ['page' => $page]);
    }

    /**
     * @param  array{title: string, header: array{logo: array{mime: string, bytes: string}|null, text: string}|null, footer: array{text: string, numbers: string}|null}  $page
     * @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper
     */
    private function pdf(string $html, array $page, array $paper, string $locale, string $title): string
    {
        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $size = $paper['size'] === 'letter' ? 'Letter' : ($paper['size'] === 'legal' ? 'Legal' : strtoupper($paper['size']));
        $m = $paper['margins'];
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $size, 'orientation' => $paper['orientation'] === 'landscape' ? 'L' : 'P', 'tempDir' => $dir,
            'margin_top' => $m['top'], 'margin_right' => $m['right'], 'margin_bottom' => $m['bottom'], 'margin_left' => $m['left'],
            'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true, 'autoArabic' => true,
            // A running header or footer pushes the content down rather than covering it.
            'setAutoTopMargin' => $page['header'] !== null ? 'stretch' : false, 'setAutoBottomMargin' => $page['footer'] !== null ? 'stretch' : false, 'autoMarginPadding' => 4,
        ]);
        $rtl = in_array($locale, self::RTL, true);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->SetTitle($title);
        $align = $rtl ? 'right' : 'left';
        if ($page['header'] !== null) {
            $logo = $page['header']['logo'] === null ? '' : '<img src="data:'.$page['header']['logo']['mime'].';base64,'.base64_encode($page['header']['logo']['bytes']).'" style="height:12mm">';
            $mpdf->SetHTMLHeader('<table width="100%" style="border-bottom:0.3mm solid #e5e7eb;font-size:9pt;color:#374151"><tr><td style="text-align:'.$align.'">'.$logo.'</td><td style="text-align:'.($rtl ? 'left' : 'right').'">'.e($page['header']['text']).'</td></tr></table>');
        }
        if ($page['footer'] !== null) {
            $number = $page['footer']['numbers'] === 'none' ? '' : e($this->pageNumber($page['footer']['numbers'], '{PAGENO}', '{nbpg}'));
            $mpdf->SetHTMLFooter('<table width="100%" style="border-top:0.3mm solid #e5e7eb;font-size:8.5pt;color:#6b7280"><tr><td style="text-align:'.$align.'">'.e($page['footer']['text']).'</td><td style="text-align:'.($rtl ? 'left' : 'right').'">'.$number.'</td></tr></table>');
        }
        // Remote resources are never fetched while rendering: the only images are the logo and codes, embedded as data.
        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
