<?php

namespace Tests\Feature\Dlp;

use App\Models\Control;
use App\Models\DlpFinding;
use App\Models\DlpPolicy;
use App\Models\Risk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlpPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_finding_can_be_linked_to_a_policy_a_risk_and_a_control(): void
    {
        $policy = DlpPolicy::factory()->create();
        $risk = Risk::factory()->create();
        $control = Control::factory()->create();

        $finding = DlpFinding::factory()->create([
            'dlp_policy_id' => $policy->getKey(),
            'risk_id' => $risk->getKey(),
            'control_id' => $control->getKey(),
        ]);

        $this->assertSame($policy->getKey(), $finding->policy?->getKey());
        $this->assertSame($risk->getKey(), $finding->risk?->getKey());
        $this->assertSame($control->getKey(), $finding->control?->getKey());
        $this->assertSame(1, $policy->findings()->count());
    }

    #[Test]
    public function a_finding_survives_a_soft_deleted_policy(): void
    {
        $policy = DlpPolicy::factory()->create();
        $finding = DlpFinding::factory()->create(['dlp_policy_id' => $policy->getKey()]);

        $policy->delete();

        $this->assertSame($policy->getKey(), $finding->refresh()->dlp_policy_id);
        $this->assertSame(1, DlpFinding::query()->count());
    }

    #[Test]
    public function removing_a_risk_clears_the_link_but_keeps_the_finding(): void
    {
        $risk = Risk::factory()->create();
        $finding = DlpFinding::factory()->create(['risk_id' => $risk->getKey()]);

        $risk->forceDelete();

        $this->assertNull($finding->refresh()->risk_id);
    }

    #[Test]
    public function a_policy_with_a_past_review_date_is_overdue(): void
    {
        $policy = DlpPolicy::factory()->overdue()->create();

        $this->assertTrue($policy->isReviewOverdue());
        $this->assertSame(1, DlpPolicy::query()->active()->count());
    }

    #[Test]
    public function an_empty_identifier_vocabulary_covers_every_type(): void
    {
        $policy = DlpPolicy::factory()->create(['identifier_types' => []]);

        $this->assertTrue($policy->coversIdentifierTypes(['email']));
        $this->assertTrue($policy->coversIdentifierTypes([]));
    }

    #[Test]
    public function a_policy_with_a_vocabulary_covers_only_its_types(): void
    {
        $policy = DlpPolicy::factory()->create(['identifier_types' => ['email', 'phone']]);

        $this->assertTrue($policy->coversIdentifierTypes(['phone']));
        $this->assertFalse($policy->coversIdentifierTypes(['credit_card']));
    }

    #[Test]
    public function the_policy_reference_is_stable_and_padded(): void
    {
        $policy = DlpPolicy::factory()->create();

        $this->assertSame('DP-0001', $policy->reference());
    }
}
