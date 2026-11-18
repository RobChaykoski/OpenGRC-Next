<?php

namespace App\Services\Documents;

use InvalidArgumentException;

/**
 * Choose the parser by file format.
 */
class DocumentTemplateParser
{
    public function __construct(
        protected DocxTemplateParser $docx,
        protected HtmlTemplateParser $html,
    ) {}

    /**
     * @return array{body: string, placeholders: array<int, string>, outline: array<int, array{level: int, text: string}>, source_sha256: string}
     */
    public function parse(string $path): array
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'docx' => $this->docx->parse($path),
            'html', 'htm' => $this->html->parse($path),
            default => throw new InvalidArgumentException('Only DOCX and HTML templates are supported.'),
        };
    }
}
