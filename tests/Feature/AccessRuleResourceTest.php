<?php

namespace Tests\Feature;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AccessRuleResource;
use App\Filament\Admin\Resources\AccessRuleResource\Pages\CreateAccessRule;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessRuleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['security.access_rules.enabled' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_rules_page_and_sees_the_empty_state(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(AccessRuleResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Access rules')
            ->assertSee('No access rules')
            ->assertSee('Add access rule');
    }

    #[Test]
    public function an_editor_and_a_viewer_cannot_open_the_rules_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
            ->get(AccessRuleResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->get(AccessRuleResource::getUrl('index'))
            ->assertForbidden();
    }

    #[Test]
    public function an_admin_can_create_a_normalised_rule(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@example.com',
        ]);
        $this->actingAs($admin);

        Livewire::test(CreateAccessRule::class)
            ->set('data.type', AccessRuleType::EmailDomain->value)
            ->set('data.effect', AccessRuleEffect::Allow->value)
            ->set('data.scope', AccessRuleScope::Access->value)
            ->set('data.value', '@Example.COM.')
            ->set('data.note', 'Corporate domain')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('access_rules', [
            'type' => AccessRuleType::EmailDomain->value,
            'effect' => AccessRuleEffect::Allow->value,
            'scope' => AccessRuleScope::Access->value,
            'value' => 'example.com',
            'created_by' => $admin->id,
        ]);
    }

    #[Test]
    public function an_invalid_ip_value_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(CreateAccessRule::class)
            ->set('data.type', AccessRuleType::Ip->value)
            ->set('data.effect', AccessRuleEffect::Deny->value)
            ->set('data.scope', AccessRuleScope::Access->value)
            ->set('data.value', '203.0.113.0/99')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasFormErrors(['value']);
    }

    #[Test]
    public function the_form_refuses_a_rule_that_would_lock_the_admin_out(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@acme.com',
        ]);
        $this->actingAs($admin);

        Livewire::test(CreateAccessRule::class)
            ->set('data.type', AccessRuleType::Ip->value)
            ->set('data.effect', AccessRuleEffect::Deny->value)
            ->set('data.scope', AccessRuleScope::Access->value)
            ->set('data.value', '127.0.0.1')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasFormErrors(['effect']);

        $this->assertDatabaseCount('access_rules', 0);
    }

    #[Test]
    public function the_form_states_the_consequence_for_access_rules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(CreateAccessRule::class)
            ->assertSee('Deny wins')
            ->assertSee('The two dimensions are independent')
            ->assertFormFieldExists('type')
            ->assertFormFieldExists('effect')
            ->assertFormFieldExists('scope')
            ->assertFormFieldExists('value')
            ->assertFormFieldExists('expires_at');
    }
}
