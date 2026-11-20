<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PrivacyRequestResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\PrivacyRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivacyRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_privacy_request_register(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $request = $this->request();

        $this->actingAs($admin)
            ->get(PrivacyRequestResource::getUrl('index'))
            ->assertOk()
            ->assertSee($request->reference)
            ->assertSee('GDPR');

        $this->actingAs($admin)
            ->get(PrivacyRequestResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_read_a_request_but_not_create_one(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $request = $this->request();

        $this->actingAs($viewer)
            ->get(PrivacyRequestResource::getUrl('view', ['record' => $request]))
            ->assertOk()
            ->assertSee($request->reference)
            ->assertSee('Contact reference')
            ->assertSee('case-ref-42');

        $this->actingAs($viewer)
            ->get(PrivacyRequestResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_open_the_create_form(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(PrivacyRequestResource::getUrl('create'))
            ->assertOk();
    }

    private function request(): PrivacyRequest
    {
        return PrivacyRequest::factory()->gdpr()->create([
            'reference' => 'PR-00042',
            'requester_name' => 'A. Requester',
            'contact_reference' => 'case-ref-42',
        ]);
    }
}
