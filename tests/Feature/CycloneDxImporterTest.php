<?php

namespace Tests\Feature;

use App\Models\SbomDocument;
use App\Models\SoftwareComponent;
use App\Services\SupplyChain\CycloneDxImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CycloneDxImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_a_cyclonedx_sbom_and_its_components(): void
    {
        $document = app(CycloneDxImporter::class)->import($this->fixture());

        $this->assertSame('customer-portal', $document->name);
        $this->assertSame('cyclonedx_json', $document->format);
        $this->assertSame('1.6', $document->spec_version);
        $this->assertSame(5, $document->component_count);
        $this->assertSame(5, $document->components()->count());

        $laravel = $document->components()->where('name', 'laravel/framework')->firstOrFail();

        $this->assertSame('pkg:composer/laravel/framework@11.0.0', $laravel->purl);
        $this->assertSame('11.0.0', $laravel->version);
        $this->assertSame('Laravel', $laravel->supplier);
        $this->assertSame('MIT', $laravel->license);
        $this->assertSame('Packagist', $laravel->ecosystem);

        $angular = $document->components()->where('name', 'core')->firstOrFail();
        $this->assertSame('npm', $angular->ecosystem);

        $django = $document->components()->where('name', 'django')->firstOrFail();
        $this->assertSame('PyPI', $django->ecosystem);
    }

    #[Test]
    public function re_importing_the_same_file_updates_instead_of_duplicating(): void
    {
        $importer = app(CycloneDxImporter::class);

        $first = $importer->import($this->fixture());
        $second = $importer->import($this->fixture());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, SbomDocument::query()->count());
        $this->assertSame(5, SoftwareComponent::query()->count());
    }

    #[Test]
    public function it_previews_without_writing(): void
    {
        $preview = app(CycloneDxImporter::class)->preview($this->fixture());

        $this->assertSame('customer-portal', $preview['name']);
        $this->assertSame(5, $preview['component_count']);
        $this->assertSame(0, SbomDocument::query()->count());
    }

    #[Test]
    public function it_rejects_a_non_cyclonedx_document(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sbom-');
        file_put_contents($path, json_encode(['bomFormat' => 'SPDX', 'components' => []]));

        try {
            $this->expectException(InvalidArgumentException::class);
            app(CycloneDxImporter::class)->import($path);
        } finally {
            @unlink($path);
        }
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/sbom/cyclonedx.json');
    }
}
