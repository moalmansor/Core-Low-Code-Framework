<?php

declare(strict_types=1);

namespace App\Modules\Documents\Docx;

use App\Modules\Records\Templating\TemplateValues;
use App\Modules\Records\Templating\ValueFormat;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * A Word template (specification §4.17): reads an uploaded DOCX as untrusted
 * input and fills its `{{ placeholders }}`.
 *
 * Reading refuses what a template must not carry (§13 untrusted input): macros,
 * embedded or linked content from outside the document (external
 * relationships other than hyperlinks, altChunk imports, INCLUDE/LINK/DDE
 * fields), document type declarations (so no external entities), and
 * archives whose entry count or unpacked size is out of proportion. XML is
 * parsed without network access and without entity substitution.
 *
 * Word splits typed text into runs at will, so a placeholder can arrive in
 * pieces; each paragraph is first normalized so a placeholder sits whole in
 * the text element where it starts. A table row whose placeholders name a
 * repeater's columns (`{{ items.quantity }}`) repeats once per row of the
 * repeater.
 */
final class DocxPackage
{
    public const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const MAX_ENTRIES = 1000;

    private const MAX_UNPACKED = 100 * 1024 * 1024;

    /** The placeholders of email and document templates, with their formats (TemplateValues::TOKEN). */
    private const TOKEN = TemplateValues::TOKEN;

    /** Relationship types a template may point outside itself with. */
    private const EXTERNAL_OK = ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink'];

    /**
     * What the template holds: its placeholders, and the reasons it cannot
     * be used (empty when it can).
     *
     * @return array{tokens: list<string>, row_groups: list<string>, problems: list<string>}
     */
    public static function inspect(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return ['tokens' => [], 'row_groups' => [], 'problems' => ['not_docx']];
        }
        try {
            $problems = self::structure($zip);
            if ($problems !== []) {
                return ['tokens' => [], 'row_groups' => [], 'problems' => $problems];
            }
            $tokens = [];
            $groups = [];
            foreach (self::textParts($zip) as $name) {
                $doc = self::load((string) $zip->getFromName($name));
                if ($doc === null) {
                    return ['tokens' => [], 'row_groups' => [], 'problems' => ['bad_xml']];
                }
                $xp = self::xpath($doc);
                foreach ($xp->query('//w:instrText') ?: [] as $n) {
                    if (preg_match('/^\s*(INCLUDETEXT|INCLUDEPICTURE|LINK|DDE|DDEAUTO|IMPORT)\b/i', (string) $n->textContent) === 1) {
                        $problems[] = 'external_field';
                    }
                }
                foreach ($xp->query('//w:fldSimple') ?: [] as $n) {
                    if ($n instanceof DOMElement && preg_match('/^\s*(INCLUDETEXT|INCLUDEPICTURE|LINK|DDE|DDEAUTO|IMPORT)\b/i', $n->getAttributeNS(self::W, 'instr')) === 1) {
                        $problems[] = 'external_field';
                    }
                }
                $alt = $xp->query('//w:altChunk');
                if ($alt !== false && $alt->length > 0) {
                    $problems[] = 'external_content';
                }
                self::normalize($xp);
                foreach ($xp->query('//w:t') ?: [] as $t) {
                    preg_match_all(self::TOKEN, (string) $t->textContent, $m);
                    foreach ($m[1] as $i => $token) {
                        $tokens[strtolower($token)] = true;
                        if (trim($m[2][$i]) !== '' && ValueFormat::parse(ltrim(trim($m[2][$i]), '|')) === null) {
                            $problems[] = 'bad_format';
                        }
                    }
                }
                foreach ($xp->query('//w:tr') ?: [] as $tr) {
                    preg_match_all(self::TOKEN, (string) $tr->textContent, $m);
                    foreach ($m[1] as $token) {
                        if (str_contains($token, '.') && ! str_starts_with($token, '@')) {
                            $groups[strtolower(explode('.', $token)[0])] = true;
                        }
                    }
                }
            }

            return ['tokens' => array_keys($tokens), 'row_groups' => array_keys($groups), 'problems' => array_values(array_unique($problems))];
        } finally {
            $zip->close();
        }
    }

    /**
     * The template with its placeholders filled.
     *
     * @param  callable(string, string): string  $value  the text of a placeholder, given its path and format spec
     * @param  callable(string, list<string>): ?list<array<string, string>>  $rows  a repeater's rows (column entry => text) for the column entries a row uses (`key` or `key|format`), or null when it is not a repeater
     * @return string the filled DOCX
     */
    public static function fill(string $path, callable $value, callable $rows): string
    {
        $problems = self::inspect($path)['problems'];
        if ($problems !== []) {
            throw new RuntimeException('The Word template cannot be used: '.implode(', ', $problems));
        }
        $out = tempnam(sys_get_temp_dir(), 'lcf-docx-');
        if ($out === false || ! copy($path, $out)) {
            throw new RuntimeException('Could not prepare the document.');
        }
        $zip = new ZipArchive;
        if ($zip->open($out) !== true) {
            throw new RuntimeException('Could not open the document.');
        }
        try {
            foreach (self::textParts($zip) as $name) {
                $doc = self::load((string) $zip->getFromName($name));
                if ($doc === null) {
                    throw new RuntimeException('The Word template is not readable.');
                }
                $xp = self::xpath($doc);
                self::normalize($xp);
                self::repeatRows($xp, $rows);
                foreach (iterator_to_array($xp->query('//w:t') ?: []) as $t) {
                    if ($t instanceof DOMElement && str_contains((string) $t->textContent, '{{')) {
                        self::replace($t, (string) preg_replace_callback(self::TOKEN, static fn (array $m) => $value(strtolower($m[1]), trim($m[2])), (string) $t->textContent));
                    }
                }
                $zip->addFromString($name, (string) $doc->saveXML());
            }
        } finally {
            $zip->close();
        }
        $bytes = (string) file_get_contents($out);
        @unlink($out);

        return $bytes;
    }

    /** @return list<string> */
    private static function structure(ZipArchive $zip): array
    {
        $problems = [];
        if ($zip->numFiles > self::MAX_ENTRIES) {
            return ['too_large'];
        }
        $size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                return ['not_docx'];
            }
            $size += (int) $stat['size'];
            $name = strtolower((string) $stat['name']);
            if (str_ends_with($name, 'vbaproject.bin') || str_ends_with($name, 'vbadata.xml')) {
                $problems[] = 'macros';
            }
            if (str_starts_with($name, 'word/embeddings/') || str_starts_with($name, 'word/activex/')) {
                $problems[] = 'embedded_objects';
            }
        }
        if ($size > self::MAX_UNPACKED) {
            return ['too_large'];
        }
        $types = (string) $zip->getFromName('[Content_Types].xml');
        if ($zip->locateName('word/document.xml') === false || ! str_contains($types, 'wordprocessingml.document.main+xml')) {
            return [...$problems, 'not_docx'];
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! str_ends_with($name, '.rels')) {
                continue;
            }
            $rels = self::load((string) $zip->getFromName($name));
            if ($rels === null) {
                return [...$problems, 'bad_xml'];
            }
            foreach ($rels->getElementsByTagName('Relationship') as $rel) {
                if (strtolower($rel->getAttribute('TargetMode')) === 'external' && ! in_array($rel->getAttribute('Type'), self::EXTERNAL_OK, true)) {
                    $problems[] = 'external_content';
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /** @return list<string> the parts that hold text: the body, headers, footers, footnotes and endnotes */
    private static function textParts(ZipArchive $zip): array
    {
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^word/(document|header\d*|footer\d*|footnotes|endnotes)\.xml$#', $name) === 1) {
                $out[] = $name;
            }
        }

        return $out;
    }

    private static function load(string $xml): ?DOMDocument
    {
        if ($xml === '' || preg_match('/<!DOCTYPE/i', $xml) === 1) {
            return null;
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok && $doc->doctype === null ? $doc : null;
    }

    private static function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w', self::W);

        return $xp;
    }

    /** Joins each placeholder split across text elements into the element where it starts. */
    private static function normalize(DOMXPath $xp): void
    {
        foreach (iterator_to_array($xp->query('//w:p') ?: []) as $p) {
            $texts = [];
            foreach ($xp->query('.//w:t', $p) ?: [] as $t) {
                if ($t instanceof DOMElement && self::paragraphOf($t) === $p) {
                    $texts[] = $t;
                }
            }
            if (count($texts) < 2) {
                continue;
            }
            $all = implode('', array_map(static fn (DOMElement $t) => (string) $t->textContent, $texts));
            if (! str_contains($all, '{{') || preg_match_all(self::TOKEN, $all, $m, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            // Owner of each byte: the element it is in, except that a placeholder's bytes all belong to the element it starts in.
            $owner = [];
            foreach ($texts as $i => $t) {
                $owner = [...$owner, ...array_fill(0, strlen((string) $t->textContent), $i)];
            }
            foreach ($m[0] as [$whole, $pos]) {
                for ($k = $pos; $k < $pos + strlen($whole); $k++) {
                    $owner[$k] = $owner[$pos];
                }
            }
            $parts = array_fill(0, count($texts), '');
            foreach ($owner as $k => $i) {
                $parts[$i] .= $all[$k];
            }
            foreach ($texts as $i => $t) {
                if ($parts[$i] !== (string) $t->textContent) {
                    $t->textContent = $parts[$i];
                    $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                }
            }
        }
    }

    private static function paragraphOf(DOMNode $n): ?DOMNode
    {
        for ($c = $n->parentNode; $c !== null; $c = $c->parentNode) {
            if ($c->localName === 'p' && $c->namespaceURI === self::W) {
                return $c;
            }
        }

        return null;
    }

    /** @param  callable(string, list<string>): ?list<array<string, string>>  $rows */
    private static function repeatRows(DOMXPath $xp, callable $rows): void
    {
        foreach (iterator_to_array($xp->query('//w:tr') ?: []) as $tr) {
            if (! $tr instanceof DOMElement || $tr->parentNode === null) {
                continue;
            }
            preg_match_all(self::TOKEN, (string) $tr->textContent, $m);
            $columns = [];
            $group = null;
            foreach ($m[1] as $i => $token) {
                $parts = explode('.', strtolower($token));
                if (count($parts) === 2 && ! str_starts_with($parts[0], '@')) {
                    $group ??= $parts[0];
                    if ($parts[0] === $group) {
                        $columns[] = self::entry($parts[1], $m[2][$i]);
                    }
                }
            }
            if ($group === null) {
                continue;
            }
            $data = $rows($group, array_values(array_unique($columns)));
            if ($data === null) {
                continue;
            }
            foreach ($data as $row) {
                $copy = $tr->cloneNode(true);
                $tr->parentNode->insertBefore($copy, $tr);
                foreach (iterator_to_array($xp->query('.//w:t', $copy) ?: []) as $t) {
                    if ($t instanceof DOMElement && str_contains((string) $t->textContent, '{{')) {
                        $text = (string) preg_replace_callback('/\{\{\s*'.preg_quote($group, '/').'\.([a-z0-9_]+)\s*((?:\|\s*[a-z]+\s*(?::\s*[A-Za-z0-9_]+\s*)?)*)\}\}/i', static fn (array $c) => $row[self::entry(strtolower($c[1]), $c[2])] ?? '', (string) $t->textContent);
                        self::replace($t, $text);
                    }
                }
            }
            $tr->parentNode->removeChild($tr);
        }
    }

    /** A repeater column as the rows callback names it: `key`, or `key|format` with its formats written without spaces. */
    private static function entry(string $column, string $spec): string
    {
        $spec = (string) preg_replace('/\s+/', '', ltrim(trim($spec), '|'));

        return $spec === '' ? $column : $column.'|'.$spec;
    }

    /** Sets a text element's text, turning line breaks into Word breaks. */
    private static function replace(DOMElement $t, string $text): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [''];
        $t->textContent = (string) array_shift($lines);
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $after = $t;
        foreach ($lines as $line) {
            $doc = $t->ownerDocument;
            if ($doc === null || $after->parentNode === null) {
                return;
            }
            $br = $doc->createElementNS(self::W, 'w:br');
            $next = $doc->createElementNS(self::W, 'w:t');
            $next->textContent = $line;
            $next->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $after->parentNode->insertBefore($br, $after->nextSibling);
            $after->parentNode->insertBefore($next, $br->nextSibling);
            $after = $next;
        }
    }
}
