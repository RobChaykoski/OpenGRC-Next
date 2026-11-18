<?php

namespace Tests\Feature\Evidence;

use App\Enums\EvidenceSeverity;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\ControlValidationRuleResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Control;
use App\Models\ControlValidationRule;
use App\Models\User;
use App\Policies\ControlValidationRulePolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidationRulesUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_rule_editor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(ControlValidationRuleResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(ControlValidationRuleResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function an_editor_cannot_open_the_rule_editor(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(ControlValidationRuleResource::getUrl('index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_viewer_cannot_open_the_rule_editor(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(ControlValidationRuleResource::getUrl('index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_rule_editor_pretty_prints_an_existing_definition(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $rule = $this->rule();

        $this->actingAs($admin)
            ->get(ControlValidationRuleResource::getUrl('edit', ['record' => $rule]))
            ->assertOk()
            ->assertSee('securityUpdates');

        $this->actingAs($admin)
            ->get(ControlValidationRuleResource::getUrl('view', ['record' => $rule]))
            ->assertOk()
            ->assertSee('securityUpdates');
    }

    #[Test]
    public function only_admins_pass_the_rule_policy(): void
    {
        $rule = $this->rule();
        $policy = new ControlValidationRulePolicy;

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $rule));

        foreach ([$editor, $viewer] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->update($user, $rule));
            $this->assertFalse($policy->delete($user, $rule));
        }
    }

    protected function rule(): ControlValidationRule
    {
        return ControlValidationRule::create([
            'control_id' => Control::factory()->create()->getKey(),
            'definition' => [
                'match' => ['evidence_type' => 'patch-compliance'],
                'assert' => [['path' => 'securityUpdates', 'op' => 'eq', 'value' => 0]],
            ],
            'severity' => EvidenceSeverity::High,
            'enabled' => true,
            'version' => 1,
        ]);
    }
}
