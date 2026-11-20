<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PolicyExceptionResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\PolicyException;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyExceptionRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_exception_register(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exception = $this->exception();

        $this->actingAs($admin)
            ->get(PolicyExceptionResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Legacy payroll exception')
            ->assertSee($exception->policy->name);
    }

    #[Test]
    public function a_viewer_can_read_the_justification_and_compensating_controls(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $exception = $this->exception();

        $this->actingAs($viewer)
            ->get(PolicyExceptionResource::getUrl('view', ['record' => $exception]))
            ->assertOk()
            ->assertSee('The vendor cannot support SSO yet.')
            ->assertSee('Network allowlist and a monthly review.');
    }

    #[Test]
    public function the_register_is_read_only(): void
    {
        $exception = $this->exception();

        $this->assertFalse(PolicyExceptionResource::canCreate());
        $this->assertFalse(PolicyExceptionResource::canEdit($exception));
        $this->assertFalse(PolicyExceptionResource::canDelete($exception));
        $this->assertFalse(PolicyExceptionResource::canDeleteAny());
    }

    private function exception(): PolicyException
    {
        return PolicyException::factory()->approved()->create([
            'name' => 'Legacy payroll exception',
            'justification' => 'The vendor cannot support SSO yet.',
            'risk_assessment' => 'A shared credential increases the blast radius.',
            'compensating_controls' => 'Network allowlist and a monthly review.',
            'expiration_date' => today()->addMonth(),
        ]);
    }
}
