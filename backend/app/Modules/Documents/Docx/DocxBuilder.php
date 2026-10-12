<?php

declare(strict_types=1);

namespace App\Modules\Documents\Docx;

use RuntimeException;
use ZipArchive;

/**
 * Writes a Word document from a resolved document design (§4.17 "HTML to
 * DOCX"): headings, paragraphs with bold runs, field lists, tables, dividers,
 * spacers and page breaks, on the template's paper, right to left for
 * right-to-left languages. All text arrives as plain text and is escaped
 * here; nothing from the design is written as markup.
 *
 * @phpstan-type Run array{text: string, bold: bool}
 * @phpstan-type Block array{type: string, runs?: list<Run>, paragraphs?: list<list<Run>>, rows?: list<array{label: string, value: string}>, head?: list<string>, cells?: list<list<string>>}
 */
final class DocxBuilder
{
    /** Paper sizes in millimetres (width, height, portrait). */
    public const PAPER = ['a4' => [210, 297], 'a3' => [297, 420], 'a5' => [148, 210], 'letter' => [216, 279], 'legal' => [216, 356]];

    /**
     * @param  list<Block>  $blocks
     * @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper
     */
    public function build(array $blocks, array $paper, bool $rtl, string $title, string $accent): string
    {
        $body = '';
        foreach ($blocks as $b) {
            $body .= match ($b['type']) {
                'heading' => $this->paragraph($b['runs'] ?? [], $rtl, '<w:pStyle w:val="Heading1"/>'),
                'text' => implode('', array_map(fn (array $runs) => $this->paragraph($runs, $rtl), $b['paragraphs'] ?? [])),
                'fields' => $this->fields($b['rows'] ?? [], $rtl),
                'table' => $this->table($b['head'] ?? [], $b['cells'] ?? [], $rtl),
                'divider' => '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="D1D5DB"/></w:pBdr>'.($rtl ? '<w:bidi/>' : '').'</w:pPr></w:p>',
                'spacer' => '<w:p/>',
                'page_break' => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
                default => '',
            };
        }
        [$w, $h] = self::PAPER[$paper['size']] ?? self::PAPER['a4'];
        if ($paper['orientation'] === 'landscape') {
            [$w, $h] = [$h, $w];
        }
        $tw = static fn (int|float $mm): int => (int) round($mm * 56.6929);
        $m = $paper['margins'];
        $sect = '<w:sectPr><w:pgSz w:w="'.$tw($w).'" w:h="'.$tw($h).'"'.($paper['orientation'] === 'landscape' ? ' w:orient="landscape"' : '').'/>'
            .'<w:pgMar w:top="'.$tw($m['top']).'" w:right="'.$tw($m['right']).'" w:bottom="'.$tw($m['bottom']).'" w:left="'.$tw($m['left']).'" w:header="708" w:footer="708" w:gutter="0"/>'
            .($rtl ? '<w:bidi/>' : '').'</w:sectPr>';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="'.DocxPackage::W.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.$sect.'</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'lcf-docx-');
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the document.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>'.$this->esc($title).'</dc:title></cp:coreProperties>');
        $zip->addFromString('word/styles.xml', $this->styles(ltrim($accent, '#')));
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /** @param  list<array{text: string, bold: bool}>  $runs */
    private function paragraph(array $runs, bool $rtl, string $style = ''): string
    {
        $out = '';
        foreach ($runs as $r) {
            $lines = preg_split('/\r\n|\r|\n/', $r['text']) ?: [''];
            $props = '<w:rPr>'.($r['bold'] ? '<w:b/><w:bCs/>' : '').($rtl ? '<w:rtl/>' : '').'</w:rPr>';
            $content = implode('<w:br/>', array_map(fn (string $l) => '<w:t xml:space="preserve">'.$this->esc($l).'</w:t>', $lines));
            $out .= '<w:r>'.$props.$content.'</w:r>';
        }

        return '<w:p><w:pPr>'.$style.($rtl ? '<w:bidi/>' : '').'</w:pPr>'.$out.'</w:p>';
    }

    /** @param  list<array{label: string, value: string}>  $rows */
    private function fields(array $rows, bool $rtl): string
    {
        if ($rows === []) {
            return '';
        }
        $out = '<w:tbl><w:tblPr>'.($rtl ? '<w:bidiVisual/>' : '').'<w:tblW w:w="5000" w:type="pct"/><w:tblLayout w:type="autofit"/></w:tblPr><w:tblGrid><w:gridCol w:w="3000"/><w:gridCol w:w="6000"/></w:tblGrid>';
        foreach ($rows as $r) {
            $out .= '<w:tr><w:tc><w:tcPr><w:tcW w:w="1600" w:type="pct"/></w:tcPr>'.$this->cell($r['label'], $rtl, false, '6B7280').'</w:tc>'
                .'<w:tc><w:tcPr><w:tcW w:w="3400" w:type="pct"/></w:tcPr>'.$this->cell($r['value'] === '' ? '—' : $r['value'], $rtl, false, $r['value'] === '' ? '9CA3AF' : null).'</w:tc></w:tr>';
        }

        return $out.'</w:tbl><w:p/>';
    }

    /**
     * @param  list<string>  $head
     * @param  list<list<string>>  $cells
     */
    private function table(array $head, array $cells, bool $rtl): string
    {
        if ($head === []) {
            return '';
        }
        $border = '<w:tblBorders><w:top w:val="single" w:sz="4" w:color="D1D5DB"/><w:bottom w:val="single" w:sz="4" w:color="D1D5DB"/><w:insideH w:val="single" w:sz="4" w:color="E5E7EB"/><w:insideV w:val="single" w:sz="4" w:color="E5E7EB"/><w:left w:val="single" w:sz="4" w:color="D1D5DB"/><w:right w:val="single" w:sz="4" w:color="D1D5DB"/></w:tblBorders>';
        $out = '<w:tbl><w:tblPr>'.($rtl ? '<w:bidiVisual/>' : '').'<w:tblW w:w="5000" w:type="pct"/>'.$border.'</w:tblPr><w:tblGrid>'.str_repeat('<w:gridCol/>', count($head)).'</w:tblGrid>';
        $out .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>'.implode('', array_map(fn (string $h) => '<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="F3F4F6"/></w:tcPr>'.$this->cell($h, $rtl, true).'</w:tc>', $head)).'</w:tr>';
        foreach ($cells as $row) {
            $out .= '<w:tr>'.implode('', array_map(fn (string $c) => '<w:tc>'.$this->cell($c, $rtl).'</w:tc>', $row)).'</w:tr>';
        }

        return $out.'</w:tbl><w:p/>';
    }

    private function cell(string $text, bool $rtl, bool $bold = false, ?string $colour = null): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [''];
        $props = '<w:rPr>'.($bold ? '<w:b/><w:bCs/>' : '').($colour !== null ? '<w:color w:val="'.$colour.'"/>' : '').($rtl ? '<w:rtl/>' : '').'</w:rPr>';

        return '<w:p><w:pPr><w:spacing w:before="40" w:after="40"/>'.($rtl ? '<w:bidi/>' : '').'</w:pPr><w:r>'.$props
            .implode('<w:br/>', array_map(fn (string $l) => '<w:t xml:space="preserve">'.$this->esc($l).'</w:t>', $lines)).'</w:r></w:p>';
    }

    private function styles(string $accent): string
    {
        $font = '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial" w:eastAsia="Arial"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="'.DocxPackage::W.'">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'.$font.'<w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="1F2937"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
            .'<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="'.$this->esc($accent).'"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>'
            .'</w:styles>';
    }

    private function esc(string $s): string
    {
        // Characters XML 1.0 cannot hold are dropped rather than corrupting the file.
        return htmlspecialchars((string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
