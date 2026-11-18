<?php

namespace Tests\Unit\Documents;

use App\Services\Documents\DocxTemplateParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class DocxTemplateParserTest extends TestCase
{
    #[Test]
    public function it_reads_the_main_document_part_from_the_relationship_file(): void
    {
        $path = $this->createDocx('word/document2.xml');

        try {
            $parsed = app(DocxTemplateParser::class)->parse($path);

            $this->assertStringContainsString('Prepared for <insert org> on {{date}}.', $parsed['body']);
            $this->assertSame(['insert org', 'date'], $parsed['placeholders']);
            $this->assertSame('Scope', $parsed['outline'][0]['text']);
            $this->assertSame(1, $parsed['outline'][0]['level']);
            $this->assertStringContainsString('Score | A', $parsed['body']);
            $this->assertSame(64, mb_strlen($parsed['source_sha256']));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_falls_back_to_word_document_xml(): void
    {
        $path = $this->createDocx('word/document.xml', withRelationships: false);

        try {
            $parsed = app(DocxTemplateParser::class)->parse($path);

            $this->assertStringContainsString('Scope', $parsed['body']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_a_file_without_a_document_body(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/styles.xml', '<styles/>');
        $zip->close();

        try {
            $this->expectException(InvalidArgumentException::class);
            app(DocxTemplateParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    private function createDocx(string $documentPath, bool $withRelationships = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        if ($withRelationships) {
            $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document2.xml"/>
</Relationships>
XML);
        }

        $zip->addFromString($documentPath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:r><w:t>Scope</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>Prepared for &lt;insert org&gt; on {{date}}.</w:t></w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Score</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML);

        $zip->close();

        return $path;
    }
}
