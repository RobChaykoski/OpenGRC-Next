<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\MyPolicies;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Policy;
use App\Models\PolicyAcknowledgement;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MyPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_user_sees_an_active_policy_and_can_acknowledge_it(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $policy = $this->policy();

        $this->actingAs($user)
            ->get(MyPolicies::getUrl())
            ->assertOk()
            ->assertSee($policy->name)
            ->assertSee('Not acknowledged');

        Livewire::actingAs($user)
            ->test(MyPolicies::class)
            ->call('acknowledge', $policy->getKey())
            ->assertHasNoErrors();

        $this->assertDatabaseHas('policy_acknowledgements', [
            'policy_id' => $policy->getKey(),
            'user_id' => $user->getKey(),
            'policy_revision' => $policy->currentRevision(),
        ]);
    }

    #[Test]
    public function a_policy_update_makes_the_previous_acknowledgement_stale(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $policy = $this->policy();

        Livewire::actingAs($user)
            ->test(MyPolicies::class)
            ->call('acknowledge', $policy->getKey());

        $policy->update(['body' => 'A materially changed policy body.']);

        $this->actingAs($user)
            ->get(MyPolicies::getUrl())
            ->assertOk()
            ->assertSee('Policy updated — acknowledge again');

        Livewire::actingAs($user)
            ->test(MyPolicies::class)
            ->call('acknowledge', $policy->getKey());

        $this->assertSame(2, PolicyAcknowledgement::query()->count());
    }

    #[Test]
    public function a_retired_policy_is_not_in_the_queue(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $this->policy(['name' => 'Retired policy', 'retired_date' => today()->subDay()]);

        $this->actingAs($user)
            ->get(MyPolicies::getUrl())
            ->assertOk()
            ->assertDontSee('Retired policy');
    }

    private function policy(array $overrides = []): Policy
    {
        return Policy::factory()->create(array_merge([
            'effective_date' => today()->subMonth(),
            'retired_date' => null,
        ], $overrides));
    }
}
