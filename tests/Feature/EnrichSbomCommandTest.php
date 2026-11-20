<?php

namespace Tests\Feature;

use App\Services\SupplyChain\CycloneDxImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnrichSbomCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_dry_run_does_not_call_deps_dev(): void
    {
        Http::preventStrayRequests();

        $document = app(CycloneDxImporter::class)->import(base_path('tests/Fixtures/sbom/cyclonedx.json'));

        $this->artisan('opengrc:sbom:enrich', [
            'sbom' => $document->getKey(),
            '--dry-run' => true,
        ])->assertSuccessful();
    }
}
