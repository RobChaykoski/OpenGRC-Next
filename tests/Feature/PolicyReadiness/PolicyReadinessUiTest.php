<?php

namespace Tests\Feature\PolicyReadiness;

use App\Enums\PolicyDocumentationStatus;
use App\Enums\PolicyEffectivenessStatus;
use App\Enums\PolicyImplementationStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PolicyReadinessItemResource;
use App\Models\PolicyReadinessAssessment;
use App\Models\PolicyReadinessItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyReadinessUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function a_viewer_can_open_the_readiness_register_and_item(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $item = $this->item();

        $this->actingAs($viewer)
            ->get(PolicyReadinessItemResource::getUrl('index'))
            ->assertOk()
            ->assertSee(__('policy_readiness.stats.documentation'));

        $this->actingAs($viewer)
            ->get(PolicyReadinessItemResource::getUrl('view', ['record' => $item]))
            ->assertOk()
            ->assertSee($item->code);
    }

    #[Test]
    public function a_viewer_cannot_edit_a_readiness_item(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(PolicyReadinessItemResource::getUrl('edit', ['record' => $this->item()]))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_edit_a_readiness_item(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(PolicyReadinessItemResource::getUrl('edit', ['record' => $this->item()]))
            ->assertOk();
    }

    #[Test]
    public function the_resource_does_not_offer_ad_hoc_creation(): void
    {
        $this->assertFalse(PolicyReadinessItemResource::canCreate());
    }

    protected function item(): PolicyReadinessItem
    {
        $assessment = PolicyReadinessAssessment::create([
            'name' => 'Policy readiness test',
            'imported_at' => now(),
        ]);

        return PolicyReadinessItem::create([
            'assessment_id' => $assessment->getKey(),
            'code' => 'POL-01',
            'category' => 'Core',
            'title' => 'Access Control Policy',
            'priority' => 'High',
            'applicability' => 'Applicable',
            'documentation_status' => PolicyDocumentationStatus::NotStarted,
            'implementation_status' => PolicyImplementationStatus::NotPlanned,
            'effectiveness_status' => PolicyEffectivenessStatus::NotTested,
            'owner' => 'Security team',
            'target_date' => 'Upon approval',
            'last_reviewed' => '2026-08-10',
        ]);
    }
}
