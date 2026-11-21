<?php

namespace Tests\Feature;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Enums\UserRole;
use App\Models\AccessRule;
use App\Models\User;
use App\Services\Access\AccessRuleLockoutGuard;
use App\Services\Access\RegistrationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessRulesRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['security.access_rules.enabled' => true]);
    }

    #[Test]
    public function a_denied_email_domain_cannot_register(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Deny, 'blocked.com', AccessRuleScope::Registration);

        $this->expectException(ValidationException::class);

        app(RegistrationGuard::class)->assertAllowed('email', 'user@blocked.com', '203.0.113.10');
    }

    #[Test]
    public function an_allowed_email_domain_can_register(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Allow, 'acme.com', AccessRuleScope::Registration);

        app(RegistrationGuard::class)->assertAllowed('email', 'user@acme.com', '203.0.113.10');

        $this->assertTrue(true);
    }

    #[Test]
    public function a_registration_ip_rule_blocks_the_request(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10', AccessRuleScope::Registration);

        $this->expectException(ValidationException::class);

        app(RegistrationGuard::class)->assertAllowed('email', 'user@acme.com', '203.0.113.10');
    }

    #[Test]
    public function the_cli_refuses_a_denied_email_domain(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Deny, 'blocked.com', AccessRuleScope::Registration);

        $this->artisan('opengrc:create-user', [
            'email' => 'user@blocked.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'user@blocked.com']);
    }

    #[Test]
    public function the_cli_keeps_the_email_rule_and_skips_ip_rules(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Allow, '10.0.0.0/8', AccessRuleScope::Registration);

        $this->artisan('opengrc:create-user', [
            'email' => 'operator@acme.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'operator@acme.com']);
    }

    #[Test]
    public function an_access_deny_rule_that_matches_the_current_admin_is_refused(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@acme.com',
        ]);
        $this->actingAs($admin);

        $candidate = new AccessRule([
            'type' => AccessRuleType::Ip,
            'effect' => AccessRuleEffect::Deny,
            'scope' => AccessRuleScope::Access,
            'value' => '127.0.0.1',
            'is_active' => true,
        ]);

        $this->assertTrue(app(AccessRuleLockoutGuard::class)->wouldLockOutCurrentUser($candidate));
    }

    #[Test]
    public function removing_the_only_allow_rule_is_refused(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@acme.com',
        ]);
        $this->actingAs($admin);

        $allow = AccessRule::create([
            'type' => AccessRuleType::Ip,
            'effect' => AccessRuleEffect::Allow,
            'scope' => AccessRuleScope::Access,
            'value' => '127.0.0.1',
            'is_active' => true,
        ]);

        AccessRule::create([
            'type' => AccessRuleType::Ip,
            'effect' => AccessRuleEffect::Allow,
            'scope' => AccessRuleScope::Access,
            'value' => '10.0.0.0/8',
            'is_active' => true,
        ]);

        $this->assertTrue(app(AccessRuleLockoutGuard::class)->wouldLockOutWithout($allow));
    }

    private function rule(
        AccessRuleType $type,
        AccessRuleEffect $effect,
        string $value,
        AccessRuleScope $scope,
    ): AccessRule {
        return AccessRule::create([
            'type' => $type,
            'effect' => $effect,
            'scope' => $scope,
            'value' => $value,
            'is_active' => true,
        ]);
    }
}
