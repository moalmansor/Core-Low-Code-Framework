<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Views\PrintRenderer;
use App\Support\Csv;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Report and dashboard results as Excel, CSV or PDF (specification §4.18).
 * A pivot is written as a cross table; a chart as its table of values, with
 * the chart itself as an image in a PDF when the screen sent one.
 */
final class ReportExporter
{
    public function __construct(private readonly PrintRenderer $printer) {}

    /**
     * The table of a result: header and rows, as written to a file.
     *
     * @param  array<string, mixed>  $result
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    public static function table(array $result, string $visualization): array
    {
        if ($result['mode'] === 'detail') {
            return [array_column($result['columns'], 'label'), $result['rows']];
        }
        $dims = $result['dimensions'];
        $measures = $result['measures'];
        if ($visualization === 'pivot' && count($dims) === 2) {
            $cols = [];
            $cells = [];
            $rowOrder = [];
            foreach ($result['rows'] as $r) {
                $cols[(string) $r['labels'][1]] = true;
                $rowOrder[(string) $r['labels'][0]] = true;
                foreach ($measures as $i => $m) {
                    $cells[(string) $r['labels'][0]][(string) $r['labels'][1]][$i] = $r['values'][$i];
                }
            }
            $header = [(string) $dims[0]['label']];
            foreach (array_keys($cols) as $c) {
                foreach ($measures as $m) {
                    $header[] = count($measures) > 1 ? $c.' · '.$m['label'] : (string) $c;
                }
            }
            $rows = [];
            foreach (array_keys($rowOrder) as $rk) {
                $row = [(string) $rk];
                foreach (array_keys($cols) as $c) {
                    foreach (array_keys($measures) as $i) {
                        $row[] = $cells[$rk][$c][$i] ?? null;
                    }
                }
                $rows[] = $row;
            }

            return [$header, $rows];
        }
        $header = [...array_column($dims, 'label'), ...array_column($measures, 'label')];
        $rows = array_map(static fn ($r) => [...$r['labels'], ...$r['values']], $result['rows']);
        if ($dims !== [] && ($result['totals'] ?? []) !== []) {
            $rows[] = [(string) __('reports.total'), ...array_fill(0, count($dims) - 1, null), ...$result['totals']];
        }

        return [$header, $rows];
    }

    /**
     * One workbook: one sheet per section.
     *
     * @param  list<array{title: string, header: list<string>, rows: list<list<mixed>>}>  $sections
     */
    public function xlsx(array $sections, string $path): void
    {
        $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $taken = [];
        $headerStyle = (new Style)->setFontBold()->setBackgroundColor('DCE6F2')->setShouldWrapText();
        foreach ($sections as $i => $s) {
            $sheet = $i === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(self::sheetName($s['title'], $taken));
            $sheet->setSheetView((new SheetView)->setRightToLeft($rtl)->setFreezeRow(3));
            $writer->addRow(new Row([new StringCell($s['title'].' — '.Carbon::now(config('app.timezone', 'UTC'))->format('Y-m-d H:i'), null)], (new Style)->setFontBold()->setFontSize(13)));
            $writer->addRow(new Row(array_map(static fn ($h) => new StringCell((string) $h, null), $s['header']), $headerStyle));
            foreach ($s['rows'] as $row) {
                $writer->addRow(new Row(array_map(static fn ($v) => Cell::fromValue($v), $row)));
            }
        }
        $writer->close();
    }

    /** @param  list<string>  $header  @param  list<list<mixed>>  $rows */
    /**
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    public function csv(array $header, array $rows, string $path): void
    {
        $h = fopen($path, 'wb');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_map(static fn ($v) => Csv::cell($v), $header), ',', '"', '');
        foreach ($rows as $row) {
            // Numbers stay numbers; text that a spreadsheet would run as a formula is neutralised.
            fputcsv($h, array_map(static fn ($v) => is_int($v) || is_float($v) ? (string) $v : Csv::cell($v), $row), ',', '"', '');
        }
        fclose($h);
    }

    /**
     * One PDF: per section a heading, the chart image when one was sent, and the table.
     *
     * @param  list<array{title: string, header: list<string>, rows: list<list<mixed>>, image?: string|null, text?: string|null}>  $sections
     */
    public function pdf(string $title, array $sections, string $path, string $orientation = 'landscape'): void
    {
        $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);
        $align = $rtl ? 'right' : 'left';
        $html = '<!doctype html><html lang="'.e(app()->getLocale()).'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><style>'
            .'body{font-family:dejavusans;font-size:8.5pt}h1{font-size:13pt;margin:0 0 4pt}h2{font-size:10.5pt;margin:14pt 0 4pt}p.meta{color:#555;margin:0 0 8pt}'
            .'table{border-collapse:collapse;width:100%}th,td{border:0.5pt solid #999;padding:2pt 3pt;text-align:'.$align.';vertical-align:top}th{background:#e8eef7}'
            .'td.n{text-align:right;direction:ltr}img.chart{max-width:100%;margin:2pt 0 6pt}'
            .'</style></head><body><h1>'.e($title).'</h1><p class="meta">'.e(Carbon::now(config('app.timezone', 'UTC'))->format('Y-m-d H:i')).'</p>';
        foreach ($sections as $s) {
            if (count($sections) > 1 || $s['title'] !== $title) {
                $html .= '<h2>'.e($s['title']).'</h2>';
            }
            if (($s['text'] ?? null) !== null) {
                $html .= '<p>'.nl2br(e((string) $s['text'])).'</p>';

                continue;
            }
            if (($s['image'] ?? null) !== null) {
                $html .= '<img class="chart" src="'.$s['image'].'" alt="">';
            }
            $html .= '<table><thead><tr>'.implode('', array_map(static fn ($h) => '<th>'.e((string) $h).'</th>', $s['header'])).'</tr></thead><tbody>';
            foreach ($s['rows'] as $row) {
                $html .= '<tr>'.implode('', array_map(static fn ($v) => is_int($v) || is_float($v) ? '<td class="n">'.e(self::number($v)).'</td>' : '<td>'.e((string) ($v ?? '')).'</td>', $row)).'</tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '</body></html>';
        $pages = __('downloads.page_of', ['page' => '{PAGENO}', 'pages' => '{nbpg}']);
        file_put_contents($path, $this->printer->pdf($html, [
            'orientation' => $orientation, 'paper' => 'a4',
            'footer_html' => '<div style="font-size:7.5pt;color:#555;text-align:'.($rtl ? 'left' : 'right').'">'.$pages.'</div>',
        ]));
    }

    /**
     * A chart image sent by the screen, as a data URL safe to embed: PNG only, at most 2 MB.
     */
    public static function chartImage(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,') || strlen($value) > 2_800_000) {
            return null;
        }
        $bytes = base64_decode(substr($value, 22), true);
        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n") || @getimagesizefromstring($bytes) === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    private static function number(int|float $v): string
    {
        return is_int($v) || floor($v) === $v ? number_format($v, 0, '.', ',') : rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.');
    }

    /** @param  array<string, bool>  $taken */
    private static function sheetName(string $name, array &$taken): string
    {
        $base = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name)), 0, 28);
        $base = $base === '' ? 'Sheet' : $base;
        $candidate = $base;
        for ($n = 2; isset($taken[mb_strtolower($candidate)]); $n++) {
            $candidate = mb_substr($base, 0, 26).' '.$n;
        }
        $taken[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}
