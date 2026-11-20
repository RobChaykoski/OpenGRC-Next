<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\SbomDocumentResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\SbomDocument;
use App\Models\User;
use App\Services\SupplyChain\CycloneDxImporter;
use App\Services\SupplyChain\SbomScanner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SbomDocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_inventory_and_a_document(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $document = $this->scannedDocument();

        $this->actingAs($admin)
            ->get(SbomDocumentResource::getUrl('index'))
            ->assertOk()
            ->assertSee('customer-portal')
            ->assertSee('Open advisories');

        $this->actingAs($admin)
            ->get(SbomDocumentResource::getUrl('view', ['record' => $document]))
            ->assertOk()
            ->assertSee($document->name)
            ->assertSee('Document SHA-256');
    }

    #[Test]
    public function a_viewer_can_read_the_inventory(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $document = $this->scannedDocument();

        $this->actingAs($viewer)
            ->get(SbomDocumentResource::getUrl('view', ['record' => $document]))
            ->assertOk();
    }

    #[Test]
    public function the_inventory_is_read_only(): void
    {
        $document = $this->scannedDocument();

        $this->assertFalse(SbomDocumentResource::canCreate());
        $this->assertFalse(SbomDocumentResource::canEdit($document));
        $this->assertFalse(SbomDocumentResource::canDelete($document));
        $this->assertFalse(SbomDocumentResource::canDeleteAny());
    }

    private function scannedDocument(): SbomDocument
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
                'summary' => 'Laravel framework issue',
                'database_specific' => ['severity' => 'HIGH'],
                'affected' => [[
                    'package' => ['ecosystem' => 'Packagist', 'name' => 'laravel/framework'],
                    'ranges' => [['events' => [['introduced' => '0'], ['fixed' => '11.1.0']]]],
                ]],
            ]),
        ]);

        $document = app(CycloneDxImporter::class)->import(base_path('tests/Fixtures/sbom/cyclonedx.json'));
        app(SbomScanner::class)->scan($document);

        return $document;
    }
}
