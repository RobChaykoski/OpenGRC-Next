<?php

namespace Tests\Feature;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Models\AccessRule;
use App\Services\Access\AccessRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessRuleEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['security.access_rules.enabled' => true]);
    }

    #[Test]
    public function an_empty_rule_set_allows_everyone(): void
    {
        $decision = $this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', 'user@example.com');

        $this->assertTrue($decision->allowed);
    }

    #[Test]
    public function a_matching_deny_rule_wins_over_an_allow_rule(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Allow, '203.0.113.0/24');
        $deny = $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');

        $decision = $this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', null);

        $this->assertFalse($decision->allowed);
        $this->assertSame('denied_by_rule', $decision->reason);
        $this->assertSame($deny->id, $decision->ruleId);
    }

    #[Test]
    public function an_ip_allowlist_denies_an_unmatched_address(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Allow, '10.0.0.0/8');

        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '10.1.2.3', null)->allowed);
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, '192.0.2.10', null)->allowed);
    }

    #[Test]
    public function an_email_domain_allowlist_denies_an_unmatched_user(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Allow, 'acme.com');

        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, null, 'user@acme.com')->allowed);
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, null, 'user@sub.acme.com')->allowed);
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, null, 'user@other.com')->allowed);
    }

    #[Test]
    public function the_two_allow_dimensions_are_independent(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Allow, '10.0.0.0/8');
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Allow, 'acme.com');

        // Correct IP, wrong domain.
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, '10.1.2.3', 'user@other.com')->allowed);

        // Correct domain, wrong IP.
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, '192.0.2.1', 'user@acme.com')->allowed);

        // Both correct.
        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '10.1.2.3', 'user@acme.com')->allowed);
    }

    #[Test]
    public function inactive_and_expired_rules_are_ignored(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10', active: false);
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.11', expiresAt: now()->subDay());

        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', null)->allowed);
        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.11', null)->allowed);
    }

    #[Test]
    public function scopes_do_not_affect_each_other(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10', scope: AccessRuleScope::Registration);

        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', null)->allowed);
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Registration, '203.0.113.10', null)->allowed);
    }

    #[Test]
    public function wildcard_domains_match_subdomains_but_not_the_apex(): void
    {
        $this->rule(AccessRuleType::EmailDomain, AccessRuleEffect::Allow, '*.example.com');

        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, null, 'user@sub.example.com')->allowed);
        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, null, 'user@example.com')->allowed);
    }

    #[Test]
    public function a_saved_rule_invalidates_the_cache(): void
    {
        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', null)->allowed);

        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');

        $this->assertFalse($this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', null)->allowed);
    }

    #[Test]
    public function the_kill_switch_disables_enforcement(): void
    {
        $this->rule(AccessRuleType::Ip, AccessRuleEffect::Deny, '203.0.113.10');
        config(['security.access_rules.enabled' => false]);

        $this->assertTrue($this->evaluator()->evaluate(AccessRuleScope::Access, '203.0.113.10', null)->allowed);
    }

    private function evaluator(): AccessRuleEvaluator
    {
        return app(AccessRuleEvaluator::class);
    }

    private function rule(
        AccessRuleType $type,
        AccessRuleEffect $effect,
        string $value,
        AccessRuleScope $scope = AccessRuleScope::Access,
        bool $active = true,
        mixed $expiresAt = null,
    ): AccessRule {
        return AccessRule::create([
            'type' => $type,
            'effect' => $effect,
            'scope' => $scope,
            'value' => $value,
            'is_active' => $active,
            'expires_at' => $expiresAt,
        ]);
    }
}
