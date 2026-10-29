<?php

declare(strict_types=1);

namespace App\Modules\Documents\Docx;

use RuntimeException;
use ZipArchive;

/**
 * Writes a Word document from a resolved document design (§4.17 "HTML to
 * DOCX"): the title, headings, paragraphs with bold runs, field lists,
 * tables, signature lines, QR codes and barcodes, dividers, spacers and page
 * breaks, on the template's paper with its running header (logo and text)
 * and footer (text and page number), right to left for right-to-left
 * languages. All text arrives as plain text and is escaped here; nothing
 * from the design is written as markup.
 *
 * @phpstan-type Run array{text: string, bold: bool}
 * @phpstan-type Block array{type: string, runs?: list<Run>, paragraphs?: list<list<Run>>, rows?: list<array{label: string, value: string}>, head?: list<string>, cells?: list<list<string>>, people?: list<array{label: string, name: string}>, date?: bool, image?: array{png: string, width: int, height: int}|null, caption?: string}
 */
final class DocxBuilder
{
    /** Paper sizes in millimetres (width, height, portrait). */
    public const PAPER = ['a4' => [210, 297], 'a3' => [297, 420], 'a5' => [148, 210], 'letter' => [216, 279], 'legal' => [216, 356]];

    /**
     * @param  list<Block>  $blocks
     * @param  array{size: string, orientation: string, margins: array{top: int, right: int, bottom: int, left: int}}  $paper
     * @param  array{title: string, header: array{logo: array{mime: string, bytes: string}|null, text: string}|null, footer: array{text: string, numbers: string}|null}|null  $page
     */
    public function build(array $blocks, array $paper, bool $rtl, string $title, string $accent, ?array $page = null): string
    {
        $this->media = $this->noMedia();
        $body = ($page['title'] ?? '') !== '' ? $this->paragraph([['text' => $page['title'], 'bold' => false]], $rtl, '<w:pStyle w:val="Title"/>') : '';
        foreach ($blocks as $b) {
            $body .= match ($b['type']) {
                'heading' => $this->paragraph($b['runs'] ?? [], $rtl, '<w:pStyle w:val="Heading1"/>'),
                'signature' => $this->signature($b['people'] ?? [], (bool) ($b['date'] ?? false), $rtl),
                'code' => $this->code($b, $rtl),
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
        $header = $page['header'] ?? null;
        $footer = $page['footer'] ?? null;
        $refs = ($header !== null ? '<w:headerReference w:type="default" r:id="rIdHeader"/>' : '').($footer !== null ? '<w:footerReference w:type="default" r:id="rIdFooter"/>' : '');
        $sect = '<w:sectPr>'.$refs.'<w:pgSz w:w="'.$tw($w).'" w:h="'.$tw($h).'"'.($paper['orientation'] === 'landscape' ? ' w:orient="landscape"' : '').'/>'
            .'<w:pgMar w:top="'.$tw($m['top']).'" w:right="'.$tw($m['right']).'" w:bottom="'.$tw($m['bottom']).'" w:left="'.$tw($m['left']).'" w:header="708" w:footer="708" w:gutter="0"/>'
            .($rtl ? '<w:bidi/>' : '').'</w:sectPr>';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="'.DocxPackage::W.'" xmlns:r="'.self::R.'" xmlns:wp="'.self::WP.'"><w:body>'.$body.$sect.'</w:body></w:document>';
        $bodyMedia = $this->media;
        $headerXml = null;
        $headerMedia = [];
        if ($header !== null) {
            $this->media = $this->noMedia();
            $logo = $header['logo'] === null ? '' : $this->logo($header['logo']);
            $headerXml = $this->part('hdr', '<w:tbl><w:tblPr>'.($rtl ? '<w:bidiVisual/>' : '').'<w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:bottom w:val="single" w:sz="4" w:color="E5E7EB"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol/><w:gridCol/></w:tblGrid><w:tr>'
                .'<w:tc><w:p><w:pPr>'.($rtl ? '<w:bidi/>' : '').'</w:pPr>'.$logo.'</w:p></w:tc>'
                .'<w:tc>'.$this->cell($header['text'], $rtl, false, '374151', $rtl ? 'left' : 'right').'</w:tc></w:tr></w:tbl><w:p/>');
            $headerMedia = $this->media;
        }
        $footerXml = null;
        if ($footer !== null) {
            $number = '';
            if ($footer['numbers'] !== 'none') {
                // "Page {PAGE} of {NUMPAGES}" in the document's language, with Word's own fields for the numbers.
                $parts = preg_split('/(:page|:total)/', $footer['numbers'] === 'page_of' ? (string) __('documents.page_of', ['page' => ':page', 'total' => ':total']) : (string) __('documents.page', ['page' => ':page']), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
                foreach ($parts as $part) {
                    $number .= match ($part) {
                        ':page' => $this->field('PAGE', $rtl),
                        ':total' => $this->field('NUMPAGES', $rtl),
                        '' => '',
                        default => '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="17"/>'.($rtl ? '<w:rtl/>' : '').'</w:rPr><w:t xml:space="preserve">'.$this->esc($part).'</w:t></w:r>',
                    };
                }
            }
            $footerXml = $this->part('ftr', '<w:tbl><w:tblPr>'.($rtl ? '<w:bidiVisual/>' : '').'<w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="E5E7EB"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol/><w:gridCol/></w:tblGrid><w:tr>'
                .'<w:tc>'.$this->cell($footer['text'], $rtl, false, '6B7280').'</w:tc>'
                .'<w:tc><w:p><w:pPr><w:jc w:val="'.($rtl ? 'left' : 'right').'"/>'.($rtl ? '<w:bidi/>' : '').'</w:pPr>'.$number.'</w:p></w:tc></w:tr></w:tbl>');
        }

        $path = tempnam(sys_get_temp_dir(), 'lcf-docx-');
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the document.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="gif" ContentType="image/gif"/>'
            .($headerXml !== null ? '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' : '')
            .($footerXml !== null ? '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' : '')
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .($headerXml !== null ? '<Relationship Id="rIdHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>' : '')
            .($footerXml !== null ? '<Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>' : '')
            .$this->imageRels($bodyMedia).'</Relationships>');
        foreach ([...$bodyMedia, ...$headerMedia] as $m) {
            $zip->addFromString('word/media/'.$m['file'], $m['bytes']);
        }
        if ($headerXml !== null) {
            $zip->addFromString('word/header1.xml', $headerXml);
            $zip->addFromString('word/_rels/header1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$this->imageRels($headerMedia).'</Relationships>');
        }
        if ($footerXml !== null) {
            $zip->addFromString('word/footer1.xml', $footerXml);
        }
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>'.$this->esc($title).'</dc:title></cp:coreProperties>');
        $zip->addFromString('word/styles.xml', $this->styles(ltrim($accent, '#')));
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    /** EMU (Word's unit for drawings) per pixel at 96 dpi. */
    private const EMU = 9525;

    /** @var list<array{id: string, file: string, bytes: string}> images of the part being written */
    private array $media = [];

    private static int $pictures = 0;

    /** @return list<array{id: string, file: string, bytes: string}> a part's images before any is added */
    private function noMedia(): array
    {
        return [];
    }

    /** A header or footer part. */
    private function part(string $tag, string $content): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:'.$tag.' xmlns:w="'.DocxPackage::W.'" xmlns:r="'.self::R.'" xmlns:wp="'.self::WP.'">'.$content.'</w:'.$tag.'>';
    }

    /** A Word field (PAGE, NUMPAGES) as its runs. */
    private function field(string $name, bool $rtl): string
    {
        $props = '<w:rPr><w:color w:val="6B7280"/><w:sz w:val="17"/>'.($rtl ? '<w:rtl/>' : '').'</w:rPr>';

        return '<w:r>'.$props.'<w:fldChar w:fldCharType="begin"/></w:r><w:r>'.$props.'<w:instrText xml:space="preserve"> '.$name.' </w:instrText></w:r>'
            .'<w:r>'.$props.'<w:fldChar w:fldCharType="separate"/></w:r><w:r>'.$props.'<w:t>1</w:t></w:r><w:r>'.$props.'<w:fldChar w:fldCharType="end"/></w:r>';
    }

    /** @param  list<array{id: string, file: string, bytes: string}>  $media */
    private function imageRels(array $media): string
    {
        return implode('', array_map(static fn (array $m) => '<Relationship Id="'.$m['id'].'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$m['file'].'"/>', $media));
    }

    /** An image run of the given size in pixels; the image joins the media of the part being written. */
    private function image(string $bytes, string $extension, int $width, int $height): string
    {
        $id = 'rIdImg'.(count($this->media) + 1);
        $n = ++self::$pictures;
        $this->media[] = ['id' => $id, 'file' => 'image'.$n.'.'.$extension, 'bytes' => $bytes];
        $cx = $width * self::EMU;
        $cy = $height * self::EMU;

        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:docPr id="'.$n.'" name="Picture '.$n.'"/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="'.$n.'" name="image'.$n.'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$id.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            .'</a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    /** The organization's logo, 45 pixels high; formats Word cannot show are converted to PNG. @param  array{mime: string, bytes: string}  $logo */
    private function logo(array $logo): string
    {
        $bytes = $logo['bytes'];
        $extension = match ($logo['mime']) {
            'image/jpeg' => 'jpeg', 'image/gif' => 'gif', default => 'png',
        };
        if (! in_array($logo['mime'], ['image/png', 'image/jpeg', 'image/gif'], true)) {
            $head = @getimagesizefromstring($bytes);
            $im = $head === false || $head[0] > 4096 || $head[1] > 4096 ? false : @imagecreatefromstring($bytes);
            if ($im === false) {
                return '';
            }
            ob_start();
            imagepng($im);
            $bytes = (string) ob_get_clean();
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[1] === 0) {
            return '';
        }
        $height = 45;

        return $this->image($bytes, $extension, max(1, (int) round($size[0] * $height / $size[1])), $height);
    }

    /** @param  array<string, mixed>  $b */
    private function code(array $b, bool $rtl): string
    {
        $image = $b['image'] ?? null;
        $out = '<w:p><w:pPr>'.($rtl ? '<w:bidi/>' : '').'</w:pPr>'.(is_array($image) ? $this->image($image['png'], 'png', $image['width'], $image['height']) : '').'</w:p>';
        if (($b['caption'] ?? '') !== '') {
            $out .= '<w:p><w:pPr><w:spacing w:before="0" w:after="160"/>'.($rtl ? '<w:bidi/>' : '').'</w:pPr><w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">'.$this->esc((string) $b['caption']).'</w:t></w:r></w:p>';
        }

        return $out;
    }

    /** Signature lines side by side: who signs, a line to sign on, their name and a date line. @param  list<array{label: string, name: string}>  $people */
    private function signature(array $people, bool $date, bool $rtl): string
    {
        if ($people === []) {
            return '';
        }
        $cells = '';
        foreach ($people as $p) {
            $cells .= '<w:tc>'
                .$this->cell($p['label'], $rtl, false, '6B7280')
                .'<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="1F2937"/></w:pBdr><w:spacing w:before="600" w:after="60"/>'.($rtl ? '<w:bidi/>' : '').'</w:pPr></w:p>'
                .($p['name'] !== '' ? $this->cell($p['name'], $rtl, true) : '')
                .($date ? $this->cell(__('documents.signature_date').' ____________', $rtl, false, '6B7280') : '')
                .'</w:tc>';
        }

        return '<w:p><w:pPr><w:spacing w:before="240"/></w:pPr></w:p><w:tbl><w:tblPr>'.($rtl ? '<w:bidiVisual/>' : '').'<w:tblW w:w="5000" w:type="pct"/></w:tblPr><w:tblGrid>'.str_repeat('<w:gridCol/>', count($people)).'</w:tblGrid><w:tr>'.$cells.'</w:tr></w:tbl><w:p/>';
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

    private function cell(string $text, bool $rtl, bool $bold = false, ?string $colour = null, ?string $align = null): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [''];
        $props = '<w:rPr>'.($bold ? '<w:b/><w:bCs/>' : '').($colour !== null ? '<w:color w:val="'.$colour.'"/>' : '').($rtl ? '<w:rtl/>' : '').'</w:rPr>';

        return '<w:p><w:pPr><w:spacing w:before="40" w:after="40"/>'.($align !== null ? '<w:jc w:val="'.$align.'"/>' : '').($rtl ? '<w:bidi/>' : '').'</w:pPr><w:r>'.$props
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
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
            .'<w:pPr><w:spacing w:after="240"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="'.$this->esc($accent).'"/><w:sz w:val="40"/><w:szCs w:val="40"/></w:rPr></w:style>'
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
