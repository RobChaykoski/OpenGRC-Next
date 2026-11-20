<?php

namespace Tests\Feature;

use App\Services\SupplyChain\CycloneDxImporter;
use App\Services\SupplyChain\SbomEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SbomEnricherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enriches_supported_components_with_licenses_and_scorecards(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.deps.dev/v3/systems/NPM/packages/%40angular%2Fcore/versions/17.0.0' => Http::response([
                'licenses' => ['MIT'],
                'relatedProjects' => [
                    ['projectKey' => ['id' => 'github.com/angular/angular']],
                ],
            ]),
            'https://api.deps.dev/v3/systems/PYPI/packages/django/versions/4.2.0' => Http::response([
                'licenses' => ['BSD-3-Clause'],
                'relatedProjects' => [],
            ]),
            'https://api.deps.dev/v3/projects/github.com%2Fangular%2Fangular' => Http::response([
                'scorecard' => [
                    'overallScore' => 8.7,
                    'date' => '2026-11-01',
                ],
            ]),
        ]);

        $document = app(CycloneDxImporter::class)->import(base_path('tests/Fixtures/sbom/cyclonedx.json'));
        $counts = app(SbomEnricher::class)->enrich($document);

        $this->assertSame(4, $counts['components']);
        $this->assertSame(2, $counts['enriched']);
        $this->assertSame(2, $counts['skipped']);
        $this->assertSame(1, $counts['scorecards']);

        $angular = $document->components()->where('name', 'core')->firstOrFail();
        $this->assertSame('NPM', $angular->deps_dev_system);
        $this->assertSame(8.7, $angular->scorecard_score);
        $this->assertSame('2026-11-01', $angular->scorecard_date?->toDateString());
        $this->assertNotNull($angular->deps_dev_checked_at);

        $django = $document->components()->where('name', 'django')->firstOrFail();
        $this->assertSame('PYPI', $django->deps_dev_system);
        $this->assertNull($django->scorecard_score);
    }

    #[Test]
    public function a_failed_lookup_does_not_stop_the_rest(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.deps.dev/v3/systems/NPM/packages/%40angular%2Fcore/versions/17.0.0' => Http::response([], 500),
            'https://api.deps.dev/v3/systems/PYPI/packages/django/versions/4.2.0' => Http::response([
                'licenses' => ['BSD-3-Clause'],
                'relatedProjects' => [],
            ]),
        ]);

        $document = app(CycloneDxImporter::class)->import(base_path('tests/Fixtures/sbom/cyclonedx.json'));
        $counts = app(SbomEnricher::class)->enrich($document);

        $this->assertSame(1, $counts['enriched']);
        $this->assertSame(1, $counts['failed']);

        $django = $document->components()->where('name', 'django')->firstOrFail();
        $this->assertSame('BSD-3-Clause', $django->license);
    }
}
