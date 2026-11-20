<?php

namespace Tests\Feature;

use App\Enums\EvidenceSeverity;
use App\Models\AssessmentReport;
use App\Models\Finding;
use App\Services\Assessment\CsetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsetImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_observations_as_findings(): void
    {
        $report = app(CsetImporter::class)->import($this->fixture());

        $this->assertSame('cset_json', $report->format);
        $this->assertSame('CSET', $report->tool);
        $this->assertSame('Water utility tabletop assessment', $report->name);
        $this->assertSame(4, $report->finding_count);
        $this->assertSame(4, Finding::query()->count());

        $metadata = $report->metadata;
        $this->assertSame(['NIST_CSF'], $metadata['standards']);
        $this->assertSame(['CIS_CSF'], $metadata['maturity_models']);
        $this->assertSame(2, $metadata['requirements']);
        $this->assertSame(1, $metadata['questions']);
        $this->assertSame(2, $metadata['negative_answers']);
        $this->assertSame('Moderate', $metadata['sal_level']);

        $critical = Finding::query()->where('dedupe_key', 'like', '%:103')->firstOrFail();
        $this->assertSame(EvidenceSeverity::Critical, $critical->severity);
        $this->assertStringContainsString('quarterly restore test', (string) $critical->description);

        $high = Finding::query()->where('dedupe_key', 'like', '%:101')->firstOrFail();
        $this->assertSame(EvidenceSeverity::High, $high->severity);
    }

    #[Test]
    public function it_does_not_store_pcii_comments_or_demographics(): void
    {
        app(CsetImporter::class)->import($this->fixture());

        $haystack = AssessmentReport::query()->get()->toJson()
            .Finding::query()->get()->toJson();

        foreach ([
            'Sensitive Utility Co',
            'Secret City',
            'Secret Plant',
            'Secret Site',
            'secret@example.com',
            'PCII comment',
            'Sensitive requirement text',
            'Sensitive question text',
            'Sensitive comment',
            'Sensitive feedback',
            'Jordan Lee',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $haystack);
        }
    }

    #[Test]
    public function re_importing_the_same_export_updates_instead_of_duplicating(): void
    {
        $importer = app(CsetImporter::class);

        $first = $importer->import($this->fixture());
        $second = $importer->import($this->fixture());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, AssessmentReport::query()->count());
        $this->assertSame(4, Finding::query()->count());
        $this->assertSame(2, Finding::query()->firstOrFail()->occurrences);
    }

    #[Test]
    public function it_rejects_a_document_that_is_not_a_cset_export(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cset-');
        file_put_contents($path, json_encode(['version' => '2.1.0', 'runs' => []]));

        try {
            $this->expectException(InvalidArgumentException::class);
            app(CsetImporter::class)->import($path);
        } finally {
            @unlink($path);
        }
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/cset/assessment.json');
    }
}
