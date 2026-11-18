<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * Extract text, headings, and placeholders from a DOCX file.
 *
 * The main document part is not always `word/document.xml`; the relationship
 * file is the authority, and `word/document2.xml` is a real-world variant.
 */
class DocxTemplateParser
{
    /**
     * @return array{body: string, placeholders: array<int, string>, outline: array<int, array{level: int, text: string}>, source_sha256: string}
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('The document template does not exist.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The document template is not a readable DOCX file.');
        }

        try {
            $mainPath = $this->mainDocumentPath($zip);
            $xml = $zip->getFromName($mainPath);

            if (! is_string($xml) || trim($xml) === '') {
                throw new InvalidArgumentException('The DOCX file has no document body.');
            }

            return $this->parseXml($xml, $path);
        } finally {
            $zip->close();
        }
    }

    private function mainDocumentPath(ZipArchive $zip): string
    {
        $rels = $zip->getFromName('_rels/.rels');

        if (is_string($rels) && $rels !== '') {
            $document = new DOMDocument;

            if (@$document->loadXML($rels)) {
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

                foreach ($xpath->query('//r:Relationship') as $relationship) {
                    if (! $relationship instanceof DOMElement) {
                        continue;
                    }

                    if (! str_ends_with((string) $relationship->getAttribute('Type'), '/officeDocument')) {
                        continue;
                    }

                    $target = ltrim((string) $relationship->getAttribute('Target'), '/');

                    if ($target !== '' && $zip->locateName($target) !== false) {
                        return $target;
                    }
                }
            }
        }

        foreach (['word/document.xml', 'word/document2.xml'] as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('The DOCX file has no main document part.');
    }

    /**
     * @return array{body: string, placeholders: array<int, string>, outline: array<int, array{level: int, text: string}>, source_sha256: string}
     */
    private function parseXml(string $xml, string $path): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadXML($xml)) {
                throw new InvalidArgumentException('The DOCX document body is not valid XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lines = [];
        $outline = [];

        foreach ($xpath->query('//w:body/*') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->localName === 'p') {
                $text = $this->paragraphText($node, $xpath);

                if ($text === '') {
                    continue;
                }

                $lines[] = $text;

                $style = $this->paragraphStyle($node, $xpath);

                if ($style !== null && (str_contains($style, 'Heading') || str_contains($style, 'PolicyHead') || $style === 'Title')) {
                    $outline[] = [
                        'level' => $this->headingLevel($style),
                        'text' => $text,
                    ];
                }

                continue;
            }

            if ($node->localName === 'tbl') {
                foreach ($xpath->query('.//w:tr', $node) ?: [] as $row) {
                    if (! $row instanceof DOMElement) {
                        continue;
                    }

                    $cells = [];

                    foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                        if ($cell instanceof DOMElement) {
                            $cells[] = $this->paragraphText($cell, $xpath);
                        }
                    }

                    if ($cells !== []) {
                        $lines[] = implode(' | ', $cells);
                    }
                }
            }
        }

        $body = trim(implode("\n", $lines));

        return [
            'body' => $body,
            'placeholders' => $this->placeholders($body),
            'outline' => $outline,
            'source_sha256' => hash_file('sha256', $path) ?: '',
        ];
    }

    private function paragraphText(DOMNode $node, DOMXPath $xpath): string
    {
        $parts = [];

        foreach ($xpath->query('.//w:t', $node) ?: [] as $text) {
            $parts[] = $text->textContent;
        }

        return trim(implode('', $parts));
    }

    private function paragraphStyle(DOMNode $node, DOMXPath $xpath): ?string
    {
        $style = $xpath->query('./w:pPr/w:pStyle', $node)?->item(0);

        if (! $style instanceof DOMElement) {
            return null;
        }

        $value = $style->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');

        return $value !== '' ? $value : null;
    }

    private function headingLevel(string $style): int
    {
        if (preg_match('/(\d+)/', $style, $matches) === 1) {
            return min(3, max(1, (int) $matches[1]));
        }

        return $style === 'Title' ? 1 : 2;
    }

    /**
     * @return array<int, string>
     */
    private function placeholders(string $body): array
    {
        $placeholders = [];

        foreach (['/<([^<>\n]{1,80})>/', '/\{\{\s*([^}\n]{1,80})\s*\}\}/', '/\[\[([^\]\n]{1,80})\]\]/'] as $pattern) {
            if (preg_match_all($pattern, $body, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $value = trim($match);

                if ($value !== '' && ! str_starts_with(strtolower($value), 'http')) {
                    $placeholders[] = $value;
                }
            }
        }

        return array_values(array_unique($placeholders));
    }
}
