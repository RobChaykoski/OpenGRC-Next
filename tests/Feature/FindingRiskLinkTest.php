<?php

namespace Tests\Feature;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FindingResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FindingRiskLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_finding_can_be_viewed_with_its_linked_risk(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $risk = Risk::factory()->create(['name' => 'Unpatched public service']);

        $finding = Finding::query()->create([
            'title' => 'Public service is unpatched',
            'description' => 'The internet-facing service runs an unsupported version.',
            'severity' => EvidenceSeverity::High,
            'status' => FindingStatus::Open,
            'source' => FindingSource::Manual,
            'risk_id' => $risk->getKey(),
            'identified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(FindingResource::getUrl('view', ['record' => $finding]))
            ->assertOk()
            ->assertSee('Public service is unpatched')
            ->assertSee('Unpatched public service');

        $this->actingAs($admin)
            ->get(FindingResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Unpatched public service');
    }
}
