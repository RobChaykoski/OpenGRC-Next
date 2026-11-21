<?php

namespace Tests\Feature;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Models\AccessRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessRulesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['security.access_rules.enabled' => true]);
    }

    #[Test]
    public function it_lists_rules_for_a_scope(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');

        $this->artisan('opengrc:access:rules', ['--scope' => 'access'])
            ->expectsOutputToContain('203.0.113.10')
            ->assertSuccessful();
    }

    #[Test]
    public function it_tests_a_denied_and_allowed_request(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');

        $this->artisan('opengrc:access:rules', [
            '--scope' => 'access',
            '--ip' => '203.0.113.10',
        ])->expectsOutputToContain('Denied')->assertExitCode(1);

        $this->artisan('opengrc:access:rules', [
            '--scope' => 'access',
            '--ip' => '203.0.113.11',
        ])->expectsOutputToContain('Allowed')->assertSuccessful();
    }

    #[Test]
    public function it_can_disable_a_rule_for_lockout_recovery(): void
    {
        $rule = $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');

        $this->artisan('opengrc:access:rules', ['--disable' => $rule->id])
            ->expectsOutputToContain("Disabled access rule {$rule->id}")
            ->assertSuccessful();

        $this->assertFalse($rule->fresh()->is_active);
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
