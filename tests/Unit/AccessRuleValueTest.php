<?php

namespace Tests\Unit;

use App\Support\Access\AccessRuleValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessRuleValueTest extends TestCase
{
    #[Test]
    public function it_matches_single_ip_addresses(): void
    {
        $this->assertTrue(AccessRuleValue::matchesIp('203.0.113.10', '203.0.113.10'));
        $this->assertFalse(AccessRuleValue::matchesIp('203.0.113.11', '203.0.113.10'));
        $this->assertFalse(AccessRuleValue::matchesIp('2001:db8::1', '203.0.113.10'));
    }

    #[Test]
    public function it_matches_ipv4_cidr_boundaries(): void
    {
        $this->assertTrue(AccessRuleValue::matchesIp('10.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(AccessRuleValue::matchesIp('10.255.255.255', '10.0.0.0/8'));
        $this->assertFalse(AccessRuleValue::matchesIp('11.0.0.1', '10.0.0.0/8'));

        $this->assertTrue(AccessRuleValue::matchesIp('203.0.113.10', '203.0.113.10/32'));
        $this->assertFalse(AccessRuleValue::matchesIp('203.0.113.11', '203.0.113.10/32'));

        $this->assertTrue(AccessRuleValue::matchesIp('198.51.100.7', '0.0.0.0/0'));
    }

    #[Test]
    public function it_matches_ipv6_cidr_blocks(): void
    {
        $this->assertTrue(AccessRuleValue::matchesIp('2001:db8::1', '2001:db8::/32'));
        $this->assertFalse(AccessRuleValue::matchesIp('2001:db9::1', '2001:db8::/32'));
        $this->assertTrue(AccessRuleValue::matchesIp('::1', '::1/128'));
    }

    #[Test]
    public function it_normalises_ip_values(): void
    {
        $this->assertSame('203.0.113.10', AccessRuleValue::normalize('ip', ' 203.0.113.10 '));
        $this->assertSame('203.0.113.0/24', AccessRuleValue::normalize('ip', '203.0.113.99/24'));
        $this->assertSame('2001:db8::/32', AccessRuleValue::normalize('ip', '2001:0db8::1/32'));
    }

    #[Test]
    public function it_validates_ip_and_domain_values(): void
    {
        $this->assertTrue(AccessRuleValue::isValid('ip', '203.0.113.10'));
        $this->assertTrue(AccessRuleValue::isValid('ip', '203.0.113.0/24'));
        $this->assertTrue(AccessRuleValue::isValid('ip', '2001:db8::/32'));
        $this->assertFalse(AccessRuleValue::isValid('ip', '203.0.113.0/33'));
        $this->assertFalse(AccessRuleValue::isValid('ip', 'example.com'));

        $this->assertTrue(AccessRuleValue::isValid('email_domain', 'example.com'));
        $this->assertTrue(AccessRuleValue::isValid('email_domain', '*.example.com'));
        $this->assertTrue(AccessRuleValue::isValid('email_domain', '@Example.com.'));
        $this->assertFalse(AccessRuleValue::isValid('email_domain', 'https://example.com'));
        $this->assertFalse(AccessRuleValue::isValid('email_domain', '*example.com'));
        $this->assertFalse(AccessRuleValue::isValid('email_domain', 'example'));
    }

    #[Test]
    public function it_normalises_email_domains(): void
    {
        $this->assertSame('example.com', AccessRuleValue::normalize('email_domain', '@Example.COM.'));
        $this->assertSame('*.example.com', AccessRuleValue::normalize('email_domain', '*.Example.com'));
        $this->assertSame('example.com', AccessRuleValue::normalize('email_domain', 'user@example.com'));
    }

    #[Test]
    public function it_matches_email_domains_exactly_or_by_wildcard(): void
    {
        $this->assertTrue(AccessRuleValue::matchesEmailDomain('user@example.com', 'example.com'));
        $this->assertFalse(AccessRuleValue::matchesEmailDomain('user@sub.example.com', 'example.com'));
        $this->assertTrue(AccessRuleValue::matchesEmailDomain('user@sub.example.com', '*.example.com'));
        $this->assertFalse(AccessRuleValue::matchesEmailDomain('user@example.com', '*.example.com'));
        $this->assertFalse(AccessRuleValue::matchesEmailDomain('not-an-email', 'example.com'));
    }

    #[Test]
    public function it_extracts_the_email_domain_without_storing_the_mailbox(): void
    {
        $this->assertSame('example.com', AccessRuleValue::emailDomain('user.name@Example.com'));
        $this->assertNull(AccessRuleValue::emailDomain('not-an-email'));
    }
}
