<?php

namespace Tests\Feature;

use App\Enums\EvidenceSeverity;
use App\Models\AssessmentReport;
use App\Models\Finding;
use App\Services\Assessment\SarifImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SarifImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_sarif_results_as_findings(): void
    {
        $report = app(SarifImporter::class)->import($this->fixture());

        $this->assertSame('Checkov', $report->tool);
        $this->assertSame('3.2.0', $report->tool_version);
        $this->assertSame(3, $report->finding_count);
        $this->assertSame(3, Finding::query()->count());

        $critical = Finding::query()->where('dedupe_key', 'like', '%CKV_AWS_3%')->firstOrFail();
        $this->assertSame(EvidenceSeverity::Critical, $critical->severity);

        $error = Finding::query()->where('dedupe_key', 'like', '%CKV_AWS_1%')->firstOrFail();
        $this->assertSame(EvidenceSeverity::High, $error->severity);
        $this->assertStringContainsString('infra/main.tf', $error->description);
        $this->assertStringNotContainsString('this should never be stored', $error->description);
    }

    #[Test]
    public function re_importing_the_same_report_updates_instead_of_duplicating(): void
    {
        $importer = app(SarifImporter::class);

        $first = $importer->import($this->fixture());
        $second = $importer->import($this->fixture());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, AssessmentReport::query()->count());
        $this->assertSame(3, Finding::query()->count());
        $this->assertSame(2, Finding::query()->firstOrFail()->occurrences);
    }

    #[Test]
    public function it_rejects_a_non_sarif_document(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sarif-');
        file_put_contents($path, json_encode(['version' => '1.0', 'runs' => []]));

        try {
            $this->expectException(InvalidArgumentException::class);
            app(SarifImporter::class)->import($path);
        } finally {
            @unlink($path);
        }
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/sarif/checkov.json');
    }
}
