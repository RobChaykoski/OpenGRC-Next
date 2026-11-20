<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PolicyAcknowledgementResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Policy;
use App\Models\PolicyAcknowledgement;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyAcknowledgementResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_acknowledgement_register(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $acknowledgement = $this->acknowledgement();

        $this->actingAs($admin)
            ->get(PolicyAcknowledgementResource::getUrl('index'))
            ->assertOk()
            ->assertSee($acknowledgement->policy->name)
            ->assertSee($acknowledgement->user->name)
            ->assertSee('Current');
    }

    #[Test]
    public function a_viewer_can_read_the_register(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $acknowledgement = $this->acknowledgement();

        $this->actingAs($viewer)
            ->get(PolicyAcknowledgementResource::getUrl('view', ['record' => $acknowledgement]))
            ->assertOk()
            ->assertSee($acknowledgement->policy->name);
    }

    #[Test]
    public function the_register_is_read_only(): void
    {
        $acknowledgement = $this->acknowledgement();

        $this->assertFalse(PolicyAcknowledgementResource::canCreate());
        $this->assertFalse(PolicyAcknowledgementResource::canEdit($acknowledgement));
        $this->assertFalse(PolicyAcknowledgementResource::canDelete($acknowledgement));
        $this->assertFalse(PolicyAcknowledgementResource::canDeleteAny());
    }

    private function acknowledgement(): PolicyAcknowledgement
    {
        $policy = Policy::factory()->create([
            'effective_date' => today()->subMonth(),
            'retired_date' => null,
        ]);
        $user = User::factory()->create(['name' => 'Policy Reader']);

        return PolicyAcknowledgement::create([
            'policy_id' => $policy->getKey(),
            'user_id' => $user->getKey(),
            'policy_revision' => $policy->currentRevision(),
            'policy_updated_at' => $policy->updated_at,
            'acknowledged_at' => now(),
            'method' => 'web',
        ]);
    }
}
