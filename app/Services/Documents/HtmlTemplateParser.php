<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Extract text, headings, and placeholders from a standalone HTML template.
 * Scripts and styles are discarded; the result is plain text, not raw HTML.
 */
class HtmlTemplateParser
{
    /**
     * @return array{body: string, placeholders: array<int, string>, outline: array<int, array{level: int, text: string}>, source_sha256: string}
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('The HTML template does not exist.');
        }

        $html = file_get_contents($path);

        if (! is_string($html) || trim($html) === '') {
            throw new InvalidArgumentException('The HTML template is empty.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadHTML($html)) {
                throw new InvalidArgumentException('The HTML template could not be parsed.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//script|//style|//noscript') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $lines = [];
        $outline = [];

        foreach ($xpath->query('//body//*[self::h1 or self::h2 or self::h3 or self::p or self::li or self::td or self::th]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));

            if ($text === '') {
                continue;
            }

            $lines[] = $text;

            if (in_array($node->localName, ['h1', 'h2', 'h3'], true)) {
                $outline[] = [
                    'level' => (int) substr($node->localName, 1),
                    'text' => $text,
                ];
            }
        }

        $body = trim(implode("\n", array_values(array_unique($lines))));

        return [
            'body' => $body,
            'placeholders' => $this->placeholders($body),
            'outline' => $outline,
            'source_sha256' => hash_file('sha256', $path) ?: '',
        ];
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
