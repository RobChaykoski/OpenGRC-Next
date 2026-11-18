<?php

namespace Tests\Unit;

use App\Enums\ControlStatus;
use App\Services\Evidence\ValidationRuleEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidationRuleEvaluatorTest extends TestCase
{
    #[Test]
    public function it_passes_when_every_assertion_passes(): void
    {
        $evaluation = (new ValidationRuleEvaluator)->evaluate([
            'assert' => [
                ['path' => 'securityUpdates', 'op' => 'eq', 'value' => 0],
                ['path' => 'agent.reporting', 'op' => 'eq', 'value' => true],
                ['path' => 'patchAgeDays', 'op' => 'lt', 'value' => 30],
            ],
        ], [
            'securityUpdates' => 0,
            'agent' => ['reporting' => true],
            'patchAgeDays' => 12,
        ]);

        $this->assertSame(ControlStatus::Effective, $evaluation['status']);
        $this->assertSame([], $evaluation['result']['missing']);
    }

    #[Test]
    public function a_definite_failure_wins_over_an_unknown_assertion(): void
    {
        $evaluation = (new ValidationRuleEvaluator)->evaluate([
            'assert' => [
                ['path' => 'securityUpdates', 'op' => 'eq', 'value' => 0],
                ['path' => 'patchAgeDays', 'op' => 'lt', 'value' => 30],
            ],
        ], [
            'securityUpdates' => 3,
        ]);

        $this->assertSame(ControlStatus::Failed, $evaluation['status']);
        $this->assertSame(['patchAgeDays'], $evaluation['result']['missing']);
    }

    #[Test]
    public function a_missing_value_is_unknown_never_a_pass(): void
    {
        $evaluation = (new ValidationRuleEvaluator)->evaluate([
            'assert' => [
                ['path' => 'securityUpdates', 'op' => 'eq', 'value' => 0],
            ],
        ], []);

        $this->assertSame(ControlStatus::Unknown, $evaluation['status']);
        $this->assertSame('unknown', $evaluation['result']['checks'][0]['result']);
    }

    #[Test]
    public function exists_and_missing_are_explicit_assertions(): void
    {
        $evaluator = new ValidationRuleEvaluator;

        $this->assertSame(ControlStatus::Effective, $evaluator->evaluate([
            'assert' => [['path' => 'agent.reporting', 'op' => 'exists']],
        ], ['agent' => ['reporting' => true]])['status']);

        $this->assertSame(ControlStatus::Failed, $evaluator->evaluate([
            'assert' => [['path' => 'agent.reporting', 'op' => 'exists']],
        ], [])['status']);

        $this->assertSame(ControlStatus::Effective, $evaluator->evaluate([
            'assert' => [['path' => 'disabled', 'op' => 'missing']],
        ], [])['status']);
    }

    #[Test]
    public function in_and_not_in_check_membership(): void
    {
        $evaluator = new ValidationRuleEvaluator;

        $this->assertSame(ControlStatus::Effective, $evaluator->evaluate([
            'assert' => [['path' => 'level', 'op' => 'in', 'value' => ['low', 'medium']]],
        ], ['level' => 'medium'])['status']);

        $this->assertSame(ControlStatus::Failed, $evaluator->evaluate([
            'assert' => [['path' => 'level', 'op' => 'not_in', 'value' => ['low', 'medium']]],
        ], ['level' => 'medium'])['status']);
    }

    #[Test]
    public function contains_works_for_strings_and_lists(): void
    {
        $evaluator = new ValidationRuleEvaluator;

        $this->assertSame(ControlStatus::Effective, $evaluator->evaluate([
            'assert' => [['path' => 'tls', 'op' => 'contains', 'value' => '1.3']],
        ], ['tls' => 'TLS 1.3 only'])['status']);

        $this->assertSame(ControlStatus::Effective, $evaluator->evaluate([
            'assert' => [['path' => 'admins', 'op' => 'contains', 'value' => 'ops@example.test']],
        ], ['admins' => ['ops@example.test']])['status']);
    }

    #[Test]
    public function a_rule_without_assertions_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ValidationRuleEvaluator)->evaluate(['assert' => []], []);
    }

    #[Test]
    public function an_unsupported_operator_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ValidationRuleEvaluator)->evaluate([
            'assert' => [['path' => 'x', 'op' => 'regex', 'value' => '.*']],
        ], ['x' => 1]);
    }
}
