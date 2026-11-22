<?php

namespace Tests\Feature\Access;

use App\Enums\AccessAction;
use App\Models\AccessDecision;
use App\Services\Access\ExposureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExposureServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function permissive_decisions_consume_their_weight(): void
    {
        AccessDecision::factory()->create(['risk_weight' => 8]);
        AccessDecision::factory()->create([
            'action' => AccessAction::AllowAudited,
            'risk_weight' => 3,
        ]);

        $this->assertSame(11, $this->service()->consumedBy('user', 'analyst'));
    }

    #[Test]
    public function denied_decisions_do_not_consume_exposure(): void
    {
        AccessDecision::factory()->denied()->create(['risk_weight' => 8]);

        $this->assertSame(0, $this->service()->consumedBy('user', 'analyst'));
    }

    #[Test]
    public function masked_or_filtered_decisions_do_not_consume_exposure(): void
    {
        AccessDecision::factory()->create([
            'action' => AccessAction::Mask,
            'risk_weight' => 8,
        ]);

        AccessDecision::factory()->create([
            'action' => AccessAction::Filter,
            'risk_weight' => 8,
        ]);

        $this->assertSame(0, $this->service()->consumedBy('user', 'analyst'));
    }

    #[Test]
    public function decisions_outside_the_window_do_not_count(): void
    {
        AccessDecision::factory()->create([
            'decided_at' => now()->subHours(2),
            'risk_weight' => 8,
        ]);

        $this->assertSame(0, $this->service()->consumedBy('user', 'analyst'));
    }

    #[Test]
    public function exposure_is_scoped_to_one_subject(): void
    {
        AccessDecision::factory()->create(['subject_label' => 'analyst', 'risk_weight' => 8]);
        AccessDecision::factory()->create(['subject_label' => 'other', 'risk_weight' => 8]);

        $this->assertSame(8, $this->service()->consumedBy('user', 'analyst'));
    }

    #[Test]
    public function position_reports_the_remaining_budget_and_band(): void
    {
        config([
            'access_governance.exposure.thresholds.review' => 25,
            'access_governance.exposure.thresholds.step_up' => 50,
            'access_governance.exposure.thresholds.block' => 100,
        ]);

        AccessDecision::factory()->count(4)->create(['risk_weight' => 8]);

        $position = $this->service()->position('user', 'analyst');

        $this->assertSame(100, $position['limit']);
        $this->assertSame(68, $position['remaining']);
        $this->assertSame('review', $position['band']);
    }

    protected function service(): ExposureService
    {
        return app(ExposureService::class);
    }
}
