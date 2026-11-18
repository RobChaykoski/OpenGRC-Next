<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentClassification;
use App\Enums\DocumentTemplateType;
use App\Models\DocumentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class DocumentTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_templates_from_a_manifest(): void
    {
        $this->putTemplate('document-templates/nda.docx');
        $this->putManifest([
            [
                'file' => 'document-templates/nda.docx',
                'name' => 'Confidentiality agreement',
                'code' => 'TPL-NDA',
                'type' => 'nda',
                'classification' => 'confidential',
            ],
        ]);

        $this->artisan('opengrc:document-templates:import')
            ->expectsOutputToContain('1 template(s)')
            ->assertSuccessful();

        $template = DocumentTemplate::query()->where('code', 'TPL-NDA')->firstOrFail();

        $this->assertSame('Confidentiality agreement', $template->name);
        $this->assertSame(DocumentTemplateType::Nda, $template->type);
        $this->assertSame(DocumentClassification::Confidential, $template->classification);
        $this->assertStringContainsString('Prepared for {{client}}', $template->body_text);
        $this->assertSame(['client'], $template->placeholderList());
        $this->assertNotEmpty($template->outline);
        $this->assertSame(64, mb_strlen((string) $template->source_sha256));

        $raw = DB::table('document_templates')->where('id', $template->getKey())->value('body_text');
        $this->assertStringNotContainsString('Prepared for', (string) $raw);
    }

    #[Test]
    public function a_second_run_leaves_an_unchanged_template_alone(): void
    {
        $this->putTemplate('document-templates/nda.docx');
        $this->putManifest([
            [
                'file' => 'document-templates/nda.docx',
                'name' => 'Confidentiality agreement',
                'code' => 'TPL-NDA',
                'type' => 'nda',
                'classification' => 'confidential',
            ],
        ]);

        $this->artisan('opengrc:document-templates:import')->assertSuccessful();
        $this->artisan('opengrc:document-templates:import')
            ->expectsOutputToContain('1 unchanged')
            ->assertSuccessful();

        $this->assertSame(1, DocumentTemplate::query()->count());
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        $this->putTemplate('document-templates/nda.docx');
        $this->putManifest([
            [
                'file' => 'document-templates/nda.docx',
                'name' => 'Confidentiality agreement',
                'type' => 'nda',
            ],
        ]);

        $this->artisan('opengrc:document-templates:import', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, DocumentTemplate::query()->count());
    }

    #[Test]
    public function a_changed_file_is_reparsed_even_at_the_same_path(): void
    {
        $this->putTemplate('document-templates/nda.docx');
        $this->putManifest([
            [
                'file' => 'document-templates/nda.docx',
                'name' => 'Confidentiality agreement',
                'code' => 'TPL-NDA',
                'type' => 'nda',
            ],
        ]);

        $this->artisan('opengrc:document-templates:import')->assertSuccessful();

        $this->putTemplate('document-templates/nda.docx', 'Second version for {{client}}');

        $this->artisan('opengrc:document-templates:import')
            ->expectsOutputToContain('1 updated')
            ->assertSuccessful();

        $template = DocumentTemplate::query()->where('code', 'TPL-NDA')->firstOrFail();

        $this->assertStringContainsString('Second version', (string) $template->body_text);
        $this->assertSame(1, DocumentTemplate::query()->count());
    }

    #[Test]
    public function a_missing_file_is_reported_without_creating_a_record(): void
    {
        $this->putManifest([
            [
                'file' => 'document-templates/missing.docx',
                'name' => 'Missing template',
                'type' => 'scope',
            ],
        ]);

        $this->artisan('opengrc:document-templates:import')->assertFailed();

        $this->assertSame(0, DocumentTemplate::query()->count());
    }

    #[Test]
    public function an_unknown_type_is_rejected(): void
    {
        $this->putTemplate('document-templates/nda.docx');
        $this->putManifest([
            [
                'file' => 'document-templates/nda.docx',
                'name' => 'Confidentiality agreement',
                'type' => 'contract',
            ],
        ]);

        $this->artisan('opengrc:document-templates:import')->assertFailed();

        $this->assertSame(0, DocumentTemplate::query()->count());
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function putManifest(array $templates): void
    {
        Storage::disk('private')->put(
            'document-templates/manifest.json',
            (string) json_encode(['templates' => $templates], JSON_PRETTY_PRINT),
        );
    }

    private function putTemplate(string $path, string $body = 'Prepared for {{client}}'): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $zip = new ZipArchive;
        $zip->open($temporary, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:r><w:t>Confidentiality</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>{$body}</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML);
        $zip->close();

        try {
            Storage::disk('private')->put($path, (string) file_get_contents($temporary));
        } finally {
            @unlink($temporary);
        }
    }
}
