<?php

namespace Tests\Feature;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Enums\UserRole;
use App\Models\AccessRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessRulesMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['security.access_rules.enabled' => true]);

        Route::middleware(['web', 'access.rules'])->get('/_test/access', fn (): string => 'allowed');
        Route::middleware(['api', 'access.rules'])->get('/_test/api-access', fn (): array => ['ok' => true]);
    }

    #[Test]
    public function a_denied_ip_is_blocked_before_the_route(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/_test/access')
            ->assertForbidden();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->get('/_test/access')
            ->assertOk()
            ->assertSee('allowed');
    }

    #[Test]
    public function an_ip_allowlist_blocks_an_unmatched_address(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Allow, '10.0.0.0/8');

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->get('/_test/access')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('/_test/access')
            ->assertForbidden();
    }

    #[Test]
    public function an_email_domain_rule_blocks_an_authenticated_user(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Deny, 'blocked.com');

        $blocked = User::factory()->create(['role' => UserRole::Viewer, 'email' => 'user@blocked.com']);
        $allowed = User::factory()->create(['role' => UserRole::Viewer, 'email' => 'user@allowed.com']);

        $this->actingAs($blocked)->get('/_test/access')->assertForbidden();
        $this->actingAs($allowed)->get('/_test/access')->assertOk();
    }

    #[Test]
    public function an_email_domain_allowlist_blocks_an_unmatched_user(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Allow, 'acme.com');

        $blocked = User::factory()->create(['role' => UserRole::Viewer, 'email' => 'user@other.com']);
        $allowed = User::factory()->create(['role' => UserRole::Viewer, 'email' => 'user@acme.com']);

        $this->actingAs($blocked)->get('/_test/access')->assertForbidden();
        $this->actingAs($allowed)->get('/_test/access')->assertOk();
    }

    #[Test]
    public function the_api_group_applies_ip_rules(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.20');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->getJson('/_test/api-access')
            ->assertForbidden();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21'])
            ->getJson('/_test/api-access')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    #[Test]
    public function the_kill_switch_bypasses_the_middleware(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');
        config(['security.access_rules.enabled' => false]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/_test/access')
            ->assertOk();
    }

    private function rule(AccessRuleType $type, AccessRuleEffect $effect, string $value): AccessRule
    {
        return AccessRule::create([
            'type' => $type,
            'effect' => $effect,
            'scope' => AccessRuleScope::Access,
            'value' => $value,
            'is_active' => true,
        ]);
    }
}
