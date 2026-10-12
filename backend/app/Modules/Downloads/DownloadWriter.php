<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Views\PrintRenderer;
use App\Support\Csv;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use ZipArchive;

/**
 * Writes a download (architecture §19.8 step 5): Excel with one sheet per
 * related form when the profile asks for it, a styled and frozen header, an
 * optional title row with the logo, right-to-left sheets for Arabic; CSV in
 * UTF-8 with a byte-order mark; PDF through mPDF with the profile's
 * orientation, header, footer and page numbers. Text never becomes a
 * formula: Excel stores it as text and CSV escapes risky first characters.
 */
final class DownloadWriter
{
    public const MIME = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'pdf' => 'application/pdf',
    ];

    public function __construct(
        private readonly DownloadEngine $engine,
        private readonly DownloadPaths $paths,
        private readonly PrintRenderer $printer,
        private readonly Translator $translator,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $plan
     * @param  callable(int): bool  $progress  receives rows written so far; false stops (cancelled)
     * @return array{rows: int, truncated: bool}
     */
    public function write(FormRuntime $rt, array $profile, array $plan, User $user, string $format, string $path, callable $progress): array
    {
        $headers = $this->engine->headers($rt, $plan);
        $title = $this->translator->labelOf((array) $profile['i18n']['name'], (string) $profile['key']);
        $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);

        return match ($format) {
            'csv' => $this->csv($rt, $profile, $plan, $user, $headers, $path, $progress),
            'pdf' => $this->pdf($rt, $profile, $plan, $user, $headers, $title, $rtl, $path, $progress),
            default => $this->xlsx($rt, $profile, $plan, $user, $headers, $title, $rtl, $path, $progress),
        };
    }

    /**
     * The file name from the profile's pattern: `{form}`, `{profile}`,
     * `{user}`, `{date}` or `{date:yyyyMMdd}`, `{param.key}`.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $parameters
     */
    public function fileName(FormRuntime $rt, array $profile, User $user, array $parameters): string
    {
        $now = Carbon::now(config('app.timezone', 'UTC'));
        $name = (string) preg_replace_callback('/\{([^}]+)\}/', function (array $m) use ($rt, $profile, $user, $parameters, $now): string {
            $ph = trim($m[1]);

            return match (true) {
                $ph === 'form' => $this->paths->formName($rt),
                $ph === 'profile' => $this->translator->labelOf((array) $profile['i18n']['name'], (string) $profile['key']),
                $ph === 'user' => (string) $user->name,
                $ph === 'date' => $now->format('Y-m-d'),
                str_starts_with($ph, 'date:') => $now->format(strtr(substr($ph, 5), ['yyyy' => 'Y', 'yy' => 'y', 'MM' => 'm', 'dd' => 'd', 'HH' => 'H', 'mm' => 'i', 'ss' => 's'])),
                str_starts_with($ph, 'param.') => self::paramText($parameters[substr($ph, 6)] ?? null),
                default => '',
            };
        }, (string) $profile['fileName']);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return $name === '' ? $this->paths->formName($rt) : $name;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, list<string>>  $headers
     * @return array{rows: int, truncated: bool}
     */
    private function xlsx(FormRuntime $rt, array $profile, array $plan, User $user, array $headers, string $title, bool $rtl, string $path, callable $progress): array
    {
        $o = (array) $profile['xlsx'];
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $logo = ($o['logo'] ?? false) ? $this->printer->logoData() : null;
        $logo = $logo !== null && in_array($logo['mime'], ['image/png', 'image/jpeg'], true) ? $logo : null;
        $titleRow = ($o['titleRow'] ?? false) || $logo !== null;
        $headerStyle = (new Style)->setFontBold();
        if ($o['styledHeader'] ?? true) {
            $headerStyle->setBackgroundColor('DCE6F2')->setShouldWrapText();
        }
        $sheets = [];
        $names = [];
        foreach (['' => $title, ...array_map(static fn ($s) => (string) $s['title'], $plan['sheets'])] as $key => $name) {
            $sheet = $key === '' ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(self::sheetName($name, $names));
            $view = (new SheetView)->setRightToLeft($rtl);
            if ($o['freezeHeader'] ?? true) {
                $view->setFreezeRow($titleRow && $key === '' ? 3 : 2);
            }
            $sheet->setSheetView($view);
            $columns = $key === '' ? $plan['columns'] : [null, ...$plan['sheets'][$key]['columns']];
            foreach ($columns as $i => $c) {
                $sheet->setColumnWidth((float) ($c['width'] ?? ($c === null ? 14 : 18)), $i + 1);
            }
            if ($titleRow && $key === '') {
                $text = $title.' — '.Carbon::now(config('app.timezone', 'UTC'))->format('Y-m-d H:i');
                $row = new Row([new StringCell($logo !== null ? '' : $text, null), ...($logo !== null ? [new StringCell($text, null)] : [])], (new Style)->setFontBold()->setFontSize(14));
                $writer->addRow($logo !== null ? $row->setHeight(42) : $row);
            }
            $writer->addRow(new Row(array_map(static fn ($h) => new StringCell($h, null), $headers[$key]), $headerStyle));
            $sheets[$key] = $sheet;
        }
        $styles = $this->numberStyles($plan);
        $written = 0;
        $result = $this->engine->rows($rt, $plan, $user, function (string $key, array $rows) use ($writer, $sheets, $styles, $progress, &$written): bool {
            if (! isset($sheets[$key])) {
                return true;
            }
            $writer->setCurrentSheet($sheets[$key]);
            foreach ($rows as $row) {
                $cells = [];
                foreach (array_values($row) as $i => $v) {
                    $cells[] = self::cell($v, 'xlsx', $key === '' ? ($styles[$i] ?? null) : null);
                }
                $writer->addRow(new Row($cells));
            }
            if ($key === '') {
                $written += count($rows);
            }

            return $key !== '' || $progress($written);
        });
        $writer->close();
        if ($logo !== null) {
            self::addLogo($path, $logo);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, list<string>>  $headers
     * @return array{rows: int, truncated: bool}
     */
    private function csv(FormRuntime $rt, array $profile, array $plan, User $user, array $headers, string $path, callable $progress): array
    {
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = (string) ($profile['csv']['delimiter'] ?? ',');
        // Arabic text needs the byte-order mark for spreadsheet programs to read it as UTF-8.
        $options->SHOULD_ADD_BOM = (bool) ($profile['csv']['bom'] ?? true) || app()->getLocale() === 'ar';
        $writer = new CsvWriter($options);
        $writer->openToFile($path);
        $writer->addRow(new Row(array_map(static fn ($h) => new StringCell(Csv::cell($h), null), $headers[''])));
        $written = 0;
        // CSV has one sheet: columns kept on separate sheets are left out (the builder says so).
        $result = $this->engine->rows($rt, $plan, $user, function (string $key, array $rows) use ($writer, &$written, $progress): bool {
            if ($key !== '') {
                return true;
            }
            foreach ($rows as $row) {
                $writer->addRow(new Row(array_map(static fn ($v) => self::cell($v, 'csv', null), array_values($row))));
            }
            $written += count($rows);

            return $progress($written);
        });
        $writer->close();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, list<string>>  $headers
     * @return array{rows: int, truncated: bool}
     */
    private function pdf(FormRuntime $rt, array $profile, array $plan, User $user, array $headers, string $title, bool $rtl, string $path, callable $progress): array
    {
        $bodies = [];
        $written = 0;
        $result = $this->engine->rows($rt, $plan, $user, function (string $key, array $rows) use (&$bodies, &$written, $progress): bool {
            foreach ($rows as $row) {
                $bodies[$key] = ($bodies[$key] ?? '').'<tr>'.implode('', array_map(static fn ($v) => '<td>'.e(is_bool($v) ? ($v ? '✓' : '') : (string) ($v ?? '')).'</td>', $row)).'</tr>';
            }
            if ($key === '') {
                $written += count($rows);
            }

            return $key !== '' || $progress($written);
        });
        $align = $rtl ? 'right' : 'left';
        $html = '<!doctype html><html lang="'.e(app()->getLocale()).'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><style>'
            .'body{font-family:dejavusans;font-size:8pt}h1{font-size:12pt;margin:0 0 6pt}h2{font-size:10pt;margin:12pt 0 4pt}'
            .'table{border-collapse:collapse;width:100%}th,td{border:0.5pt solid #999;padding:2pt 3pt;text-align:'.$align.';vertical-align:top}th{background:#e8eef7}'
            .'</style></head><body><h1>'.e($title).'</h1>';
        foreach (['' => null, ...array_map(static fn ($s) => (string) $s['title'], $plan['sheets'])] as $key => $sheetTitle) {
            if ($sheetTitle !== null) {
                $html .= '<h2>'.e($sheetTitle).'</h2>';
            }
            $html .= '<table><thead><tr>'.implode('', array_map(static fn ($h) => '<th>'.e($h).'</th>', $headers[$key])).'</tr></thead><tbody>'.($bodies[$key] ?? '').'</tbody></table>';
        }
        $html .= '</body></html>';
        $o = (array) $profile['pdf'];
        $header = $this->translator->pick((array) ($o['header'] ?? []));
        $footer = $this->translator->pick((array) ($o['footer'] ?? []));
        $pages = ($o['pageNumbers'] ?? true) ? __('downloads.page_of', ['page' => '{PAGENO}', 'pages' => '{nbpg}']) : null;
        file_put_contents($path, $this->printer->pdf($html, [
            'orientation' => $o['orientation'] ?? 'portrait', 'paper' => 'a4',
            'header_html' => $header === null ? null : '<div style="font-size:8pt;color:#444;text-align:'.$align.'">'.e($header).'</div>',
            'footer_html' => $footer === null && $pages === null ? null : '<table width="100%" style="font-size:7.5pt;color:#555"><tr><td>'.e((string) $footer).'</td><td style="text-align:'.($rtl ? 'left' : 'right').'">'.($pages ?? '').'</td></tr></table>',
        ]));

        return $result;
    }

    /**
     * Excel number formats for numeric columns shown in Latin digits; their
     * values stay numbers so they can be summed.
     *
     * @param  array<string, mixed>  $plan
     * @return array<int, Style>
     */
    private function numberStyles(array $plan): array
    {
        $out = [];
        foreach ($plan['columns'] as $i => $c) {
            $f = $c['format'] ?? null;
            if (! is_array($f) || ! in_array($f['type'], ['number', 'currency', 'percent'], true) || ($f['digits'] ?? 'latin') !== 'latin') {
                continue;
            }
            $decimals = (int) ($f['decimals'] ?? ($f['type'] === 'currency' ? 2 : 0));
            $pattern = '#,##0'.($decimals > 0 ? '.'.str_repeat('0', $decimals) : '');
            $out[$i] = (new Style)->setFormat(match ($f['type']) {
                'currency' => $pattern.' "'.$f['currency'].'"',
                'percent' => $pattern.'"%"',
                default => $pattern,
            });
        }

        return $out;
    }

    private static function cell(mixed $value, string $format, ?Style $style): Cell
    {
        if ($style !== null && is_string($value) && preg_match('/^-?[\d,]+(\.\d+)?( [A-Z]{3}|%)?$/', $value) === 1) {
            // A formatted number back to its value, so Excel keeps it numeric under its format.
            $value = (float) str_replace(',', '', (string) preg_replace('/( [A-Z]{3}|%)$/', '', $value));
        }
        if (is_string($value)) {
            return new StringCell($format === 'csv' ? Csv::cell($value) : $value, null);
        }
        if (is_bool($value)) {
            return new StringCell($value ? '✓' : '', null);
        }

        return Cell::fromValue(is_scalar($value) || $value === null ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE), $style);
    }

    /** @param  array<string, true>  $taken */
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

    private static function paramText(mixed $v): string
    {
        if (is_array($v)) {
            return trim(implode('–', array_filter([$v['from'] ?? null, $v['to'] ?? null])));
        }

        return is_scalar($v) ? (string) $v : '';
    }

    /**
     * Places the logo over the first cell of the first sheet: OpenSpout does
     * not write images, so the drawing parts are added to the finished file.
     *
     * @param  array{mime: string, bytes: string}  $logo
     */
    private static function addLogo(string $path, array $logo): void
    {
        $size = @getimagesizefromstring($logo['bytes']);
        if ($size === false || $size[1] <= 0) {
            return;
        }
        $height = 48;
        $width = (int) round($size[0] * $height / $size[1]);
        $ext = $logo['mime'] === 'image/png' ? 'png' : 'jpeg';
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return;
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $types = $zip->getFromName('[Content_Types].xml');
        if ($sheet === false || $types === false || str_contains($sheet, '<drawing ')) {
            $zip->close();

            return;
        }
        $emu = static fn (int $px): int => $px * 9525;
        $zip->addFromString('xl/media/lcf_logo.'.$ext, $logo['bytes']);
        $zip->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>38100</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from>'
            .'<xdr:ext cx="'.$emu($width).'" cy="'.$emu($height).'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            .'<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$emu($width).'" cy="'.$emu($height).'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>');
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/lcf_logo.'.$ext.'"/></Relationships>');
        $rels = $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
        $relation = '<Relationship Id="rIdLcfLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>';
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $rels === false
            ? '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relation.'</Relationships>'
            : str_replace('</Relationships>', $relation.'</Relationships>', $rels));
        if (! str_contains($sheet, 'xmlns:r=')) {
            $sheet = (string) preg_replace('/<worksheet /', '<worksheet xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" ', $sheet, 1);
        }
        // The drawing comes after the page settings and before any table parts.
        $sheet = preg_match('/<tableParts|<extLst/', $sheet) === 1
            ? (string) preg_replace('/(<tableParts|<extLst)/', '<drawing r:id="rIdLcfLogo"/>$1', $sheet, 1)
            : str_replace('</worksheet>', '<drawing r:id="rIdLcfLogo"/></worksheet>', $sheet);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        if (! str_contains($types, 'Extension="'.$ext.'"')) {
            $types = str_replace('</Types>', '<Default Extension="'.$ext.'" ContentType="'.$logo['mime'].'"/></Types>', $types);
        }
        $types = str_replace('</Types>', '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>', $types);
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->close();
    }
}
