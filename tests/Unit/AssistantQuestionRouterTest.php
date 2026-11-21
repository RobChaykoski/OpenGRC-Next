<?php

namespace Tests\Unit;

use App\Enums\AssistantIntent;
use App\Services\Ai\Assistant\AssistantQuestionRouter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantQuestionRouterTest extends TestCase
{
    #[Test]
    public function it_routes_the_catalogue_examples(): void
    {
        $router = new AssistantQuestionRouter;

        foreach (AssistantIntent::cases() as $intent) {
            $this->assertSame(
                $intent,
                $router->route($intent->example()),
                "Example did not route to {$intent->value}: {$intent->example()}"
            );
        }
    }

    #[Test]
    public function it_routes_common_phrasings(): void
    {
        $router = new AssistantQuestionRouter;

        $this->assertSame(AssistantIntent::OverdueTests, $router->route('Which tests are overdue?'));
        $this->assertSame(AssistantIntent::OpenFindings, $router->route('Show me the open findings'));
        $this->assertSame(AssistantIntent::RiskAppetite, $router->route('Which risks are above appetite?'));
        $this->assertSame(AssistantIntent::ExpiringExceptions, $router->route('Which exceptions are expiring?'));
        $this->assertSame(AssistantIntent::PolicyAcknowledgements, $router->route('Who has not acknowledged the policies?'));
        $this->assertSame(AssistantIntent::ControlAttestations, $router->route('Which attestations are due?'));
        $this->assertSame(AssistantIntent::NhiRotation, $router->route('Which service accounts need rotation?'));
        $this->assertSame(AssistantIntent::AuditProgress, $router->route('Which audits are still in progress?'));
        $this->assertSame(AssistantIntent::FrameworkGaps, $router->route('Which outcomes lack evidence in CSCv8?'));
    }

    #[Test]
    public function it_refuses_questions_outside_the_catalogue(): void
    {
        $router = new AssistantQuestionRouter;

        $this->assertNull($router->route('What is the capital of France?'));
        $this->assertNull($router->route('Write a poem about compliance.'));
        $this->assertNull($router->route(''));
    }
}
