<?php

namespace Tests\Feature;

use App\Enums\EvidenceSeverity;
use App\Models\Finding;
use App\Models\SoftwareVulnerability;
use App\Services\SupplyChain\CycloneDxImporter;
use App\Services\SupplyChain\SbomScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SbomScannerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_vulnerabilities_and_findings_from_osv(): void
    {
        $this->fakeOsv();

        $document = app(CycloneDxImporter::class)->import(base_path('tests/Fixtures/sbom/cyclonedx.json'));
        $counts = app(SbomScanner::class)->scan($document);

        $this->assertSame(4, $counts['components']);
        $this->assertSame(3, $counts['queried']);
        $this->assertSame(1, $counts['unsupported']);
        $this->assertSame(2, $counts['vulnerabilities']);
        $this->assertSame(2, $counts['findings_created']);

        $this->assertSame(2, SoftwareVulnerability::query()->count());
        $this->assertSame(2, Finding::query()->count());

        $critical = SoftwareVulnerability::query()->where('osv_id', 'PYSEC-1')->firstOrFail();
        $this->assertSame(EvidenceSeverity::Critical, $critical->severity);
        $this->assertSame('4.2.1', $critical->fixed_version);
        $this->assertNotNull($critical->finding_id);

        $finding = Finding::query()->where('dedupe_key', 'like', 'osv:PYSEC-1:%')->firstOrFail();
        $this->assertSame(EvidenceSeverity::Critical, $finding->severity);
    }

    #[Test]
    public function a_re_scan_updates_the_same_finding(): void
    {
        $this->fakeOsv();

        $document = app(CycloneDxImporter::class)->import(base_path('tests/Fixtures/sbom/cyclonedx.json'));
        $scanner = app(SbomScanner::class);

        $scanner->scan($document);
        $counts = $scanner->scan($document);

        $this->assertSame(2, $counts['findings_updated']);
        $this->assertSame(2, SoftwareVulnerability::query()->count());
        $this->assertSame(2, Finding::query()->count());

        $finding = Finding::query()->where('dedupe_key', 'like', 'osv:GHSA-1:%')->firstOrFail();
        $this->assertSame(2, $finding->occurrences);
    }

    private function fakeOsv(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    ['vulns' => [['id' => 'GHSA-1']]],
                    ['vulns' => []],
                    ['vulns' => [['id' => 'PYSEC-1']]],
                ],
            ]),
            'https://api.osv.dev/v1/vulns/GHSA-1' => Http::response([
                'id' => 'GHSA-1',
                'summary' => 'Laravel framework issue',
                'database_specific' => ['severity' => 'HIGH'],
                'affected' => [[
                    'package' => ['ecosystem' => 'Packagist', 'name' => 'laravel/framework'],
                    'ranges' => [['events' => [['introduced' => '0'], ['fixed' => '11.1.0']]]],
                ]],
            ]),
            'https://api.osv.dev/v1/vulns/PYSEC-1' => Http::response([
                'id' => 'PYSEC-1',
                'summary' => 'Django issue',
                'database_specific' => ['severity' => 'CRITICAL'],
                'affected' => [[
                    'package' => ['ecosystem' => 'PyPI', 'name' => 'django'],
                    'ranges' => [['events' => [['introduced' => '0'], ['fixed' => '4.2.1']]]],
                ]],
            ]),
        ]);
    }
}
