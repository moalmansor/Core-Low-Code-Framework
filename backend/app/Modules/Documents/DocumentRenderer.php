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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

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
    ) {}

    /**
     * @param  list<User>  $viewers  whose field access applies
     * @return array{bytes: string, name: string, extension: string, mime: string}
     */
    public function render(DocumentTemplate $t, ?FormRuntime $rt, ?int $recordId, array $viewers, string $locale, string $format): array
    {
        if (! in_array($format, ['docx', 'pdf'], true)) {
            throw new RuntimeException("Unknown document format {$format}.");
        }
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            $ctx = $this->values->context($rt, $recordId, $viewers, $locale, count($viewers) === 1 ? $viewers[0]->name : null);
            $name = $this->name($t, $ctx);
            if ($t->type === 'docx') {
                $docx = $this->fillWord($t, $ctx);
                $bytes = $format === 'pdf' ? $this->converter->convert($docx) : $docx;
            } else {
                $blocks = $this->resolve((array) $t->design, $ctx);
                $bytes = $format === 'pdf'
                    ? $this->pdf($this->html($blocks, $t->paper, $locale, $name, $this->values->accent((array) $t->design)), $t->paper, $locale)
                    : $this->builder->build($blocks, $t->paper, in_array($locale, self::RTL, true), $name, $this->values->accent((array) $t->design));
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
            $ctx = $this->values->context($rt, $recordId, $viewers, $locale, $viewers[0]->name ?? null);

            return $this->html($this->resolve($design, $ctx), $paper, $locale, $title, $this->values->accent($design), true);
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
                    $table = $this->values->table((string) ($b['group'] ?? ''), array_values(array_map('strval', (array) ($b['columns'] ?? []))), $ctx);
                    if ($table !== null) {
                        $out[] = ['type' => 'table', 'head' => $table['head'], 'cells' => $table['rows']];
                    }
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
                fn (string $token) => $this->values->value($token, $ctx),
                function (string $group, array $columns) use ($ctx): ?array {
                    if ($this->values->repeater($ctx->rt, $group) === null) {
                        return null;
                    }
                    $table = $this->values->table($group, $columns, $ctx);
                    if ($table === null) {
                        return [];
                    }

                    return array_map(static fn (array $row) => array_combine($table['keys'], $row), $table['rows']);
                },
            );
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper
     */
    private function html(array $blocks, array $paper, string $locale, string $title, string $accent, bool $screen = false): string
    {
        $rtl = in_array($locale, self::RTL, true);
        $runs = static fn (array $rs): string => implode('', array_map(static fn (array $r) => $r['bold'] ? '<b>'.nl2br(e($r['text'])).'</b>' : nl2br(e($r['text'])), $rs));
        $body = '';
        foreach ($blocks as $b) {
            $body .= match ($b['type']) {
                'heading' => '<h1>'.$runs($b['runs'] ?? []).'</h1>',
                'text' => implode('', array_map(static fn (array $p) => '<p>'.$runs($p).'</p>', $b['paragraphs'] ?? [])),
                'fields' => ($b['rows'] ?? []) === [] ? '' : '<table class="fields">'.implode('', array_map(static fn (array $r) => '<tr><th>'.e($r['label']).'</th><td>'.($r['value'] === '' ? '<span class="empty">—</span>' : nl2br(e($r['value']))).'</td></tr>', $b['rows'])).'</table>',
                'table' => '<table class="grid"><thead><tr>'.implode('', array_map(static fn (string $h) => '<th>'.e($h).'</th>', $b['head'] ?? [])).'</tr></thead><tbody>'
                    .implode('', array_map(static fn (array $row) => '<tr>'.implode('', array_map(static fn (string $c) => '<td>'.nl2br(e($c)).'</td>', $row)).'</tr>', $b['cells'] ?? [])).'</tbody></table>',
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
            .'h1{font-size:18pt;color:'.$accent.';margin:0 0 8pt}p{margin:0 0 8pt}hr{border:0;border-top:1px solid #d1d5db;margin:10pt 0}.spacer{height:12pt}'
            .'table{border-collapse:collapse;margin:4pt 0 10pt}table.fields th{text-align:'.($rtl ? 'right' : 'left').';color:#6b7280;font-weight:normal;padding:3pt '.($rtl ? '0 3pt 16pt' : '16pt 3pt 0').';vertical-align:top;white-space:nowrap}'
            .'table.fields td{padding:3pt 0}.empty{color:#9ca3af}table.grid{width:100%}table.grid th{background:#f3f4f6;font-weight:bold;text-align:'.($rtl ? 'right' : 'left').'}'
            .'table.grid th,table.grid td{border:1px solid #e5e7eb;padding:4pt 6pt;vertical-align:top}';
        if ($screen) {
            $css .= 'html{background:#e5e7eb}body{background:#fff;width:'.$w.'mm;min-height:'.$h.'mm;box-sizing:border-box;margin:16px auto;padding:'.$m['top'].'mm '.$m['right'].'mm '.$m['bottom'].'mm '.$m['left'].'mm;box-shadow:0 1px 4px rgba(0,0,0,.15)}'
                .'.page-break{border-top:2px dashed #9ca3af;margin:16px 0}';
        }

        return '<!doctype html><html lang="'.e($locale).'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    /** @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper */
    private function pdf(string $html, array $paper, string $locale): string
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
        ]);
        $mpdf->SetDirectionality(in_array($locale, self::RTL, true) ? 'rtl' : 'ltr');
        // Remote resources are never fetched while rendering: the design holds no images or links.
        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
