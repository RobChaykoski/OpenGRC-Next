<?php

namespace Tests\Unit\Documents;

use App\Services\Documents\HtmlTemplateParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlTemplateParserTest extends TestCase
{
    #[Test]
    public function it_extracts_text_headings_and_placeholders_without_scripts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'html-').'.html';

        file_put_contents($path, <<<'HTML'
<!doctype html>
<html>
<head><style>.x { color: red; }</style></head>
<body>
  <h1>Assessment</h1>
  <p>Prepared for {{insert_org}}.</p>
  <h2>Findings</h2>
  <ul><li>Finding {{number}}</li></ul>
  <script>alert('not text')</script>
</body>
</html>
HTML);

        try {
            $parsed = app(HtmlTemplateParser::class)->parse($path);

            $this->assertStringContainsString('Prepared for {{insert_org}}.', $parsed['body']);
            $this->assertStringNotContainsString('alert', $parsed['body']);
            $this->assertSame('Assessment', $parsed['outline'][0]['text']);
            $this->assertSame(1, $parsed['outline'][0]['level']);
            $this->assertSame(2, $parsed['outline'][1]['level']);
            $this->assertContains('insert_org', $parsed['placeholders']);
            $this->assertContains('number', $parsed['placeholders']);
        } finally {
            @unlink($path);
        }
    }
}
