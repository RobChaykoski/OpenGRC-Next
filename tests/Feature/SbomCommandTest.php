<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\SbomDocument;
use App\Models\SoftwareComponent;
use App\Services\SupplyChain\CycloneDxImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SbomCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_import_command_can_preview_without_writing(): void
    {
        $this->artisan('opengrc:sbom:import', [
            'path' => $this->fixture(),
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, SbomDocument::query()->count());
        $this->assertSame(0, SoftwareComponent::query()->count());
    }

    #[Test]
    public function the_import_command_writes_a_document_and_components(): void
    {
        $this->artisan('opengrc:sbom:import', ['path' => $this->fixture()])
            ->expectsOutputToContain('component(s)')
            ->assertSuccessful();

        $this->assertSame(1, SbomDocument::query()->count());
        $this->assertSame(5, SoftwareComponent::query()->count());
    }

    #[Test]
    public function the_import_command_can_scan_with_osv(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    ['vulns' => [['id' => 'GHSA-1']]],
                    ['vulns' => []],
                    ['vulns' => []],
                ],
            ]),
            'https://api.osv.dev/v1/vulns/GHSA-1' => Http::response([
                'id' => 'GHSA-1',
                'summary' => 'Example',
                'database_specific' => ['severity' => 'HIGH'],
            ]),
        ]);

        $this->artisan('opengrc:sbom:import', [
            'path' => $this->fixture(),
            '--scan' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Finding::query()->count());
    }

    #[Test]
    public function the_scan_command_reports_without_calling_osv_on_a_dry_run(): void
    {
        $document = app(CycloneDxImporter::class)->import($this->fixture());

        Http::preventStrayRequests();

        $this->artisan('opengrc:sbom:scan', [
            'sbom' => $document->getKey(),
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/sbom/cyclonedx.json');
    }
}
