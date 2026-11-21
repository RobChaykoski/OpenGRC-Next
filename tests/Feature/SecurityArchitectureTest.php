<?php

namespace Tests\Feature;

use App\Enums\ControlStatus;
use App\Enums\UserRole;
use App\Filament\Pages\SecurityArchitecture;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\Policy;
use App\Models\User;
use App\Services\Assurance\SecurityArchitectureView;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function it_places_outcomes_in_the_six_csf_functions_and_reports_current_state(): void
    {
        $this->seedArchitecture();

        $view = app(SecurityArchitectureView::class)->build('test_framework');

        $this->assertSame(6, $view['outcomes']);
        $this->assertSame(1, $view['counts']['evidenced']);
        $this->assertSame(1, $view['counts']['documented']);
        $this->assertSame(1, $view['counts']['stale']);
        $this->assertSame(1, $view['counts']['failing']);
        $this->assertSame(2, $view['counts']['gap']);

        $functions = collect($view['functions'])->keyBy('key');

        $this->assertSame('gap', $functions['govern']['outcomes'][0]['state']);
        $this->assertSame('documented', $functions['identify']['outcomes'][0]['state']);
        $this->assertSame('evidenced', $functions['protect']['outcomes'][0]['state']);
        $this->assertSame('failing', $functions['detect']['outcomes'][0]['state']);
        $this->assertSame('stale', $functions['respond']['outcomes'][0]['state']);
        $this->assertSame('gap', $functions['recover']['outcomes'][0]['state']);
    }

    #[Test]
    public function a_viewer_can_open_the_current_state_page(): void
    {
        $this->seedArchitecture();

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(SecurityArchitecture::getUrl(['framework' => 'test_framework']))
            ->assertOk()
            ->assertSee('Security architecture')
            ->assertSee('Govern')
            ->assertSee('Protect')
            ->assertSee('Current state only');
    }

    private function seedArchitecture(): void
    {
        $framework = CanonicalFramework::create([
            'code' => 'test_framework',
            'name' => 'Test framework',
            'priority' => 1,
        ]);

        $governance = $this->outcome('GOV-1', 'Governance');
        $identify = $this->outcome('ID-1', 'Asset Management');
        $protect = $this->outcome('PR-1', 'Identity');
        $detect = $this->outcome('DE-1', 'Monitoring');
        $respond = $this->outcome('RS-1', 'Security Operations', 'weekly');
        $recover = $this->outcome('RC-1', 'Business Continuity');

        $framework->controls()->attach([
            $governance->getKey(),
            $identify->getKey(),
            $protect->getKey(),
            $detect->getKey(),
            $respond->getKey(),
            $recover->getKey(),
        ]);

        $identifyControl = Control::factory()->create(['code' => 'ID-C']);
        $identifyControl->canonicalOutcomes()->attach($identify->getKey());
        Policy::factory()->create()->canonicalOutcomes()->attach($identify->getKey());

        $protectControl = Control::factory()->create(['code' => 'PR-C']);
        $protectControl->canonicalOutcomes()->attach($protect->getKey());
        ControlValidation::create([
            'control_id' => $protectControl->getKey(),
            'status' => ControlStatus::Effective,
            'evaluated_at' => now(),
        ]);

        $detectControl = Control::factory()->create(['code' => 'DE-C']);
        $detectControl->canonicalOutcomes()->attach($detect->getKey());
        ControlValidation::create([
            'control_id' => $detectControl->getKey(),
            'status' => ControlStatus::Failed,
            'evaluated_at' => now(),
        ]);

        $respondControl = Control::factory()->create(['code' => 'RS-C']);
        $respondControl->canonicalOutcomes()->attach($respond->getKey());
        ControlValidation::create([
            'control_id' => $respondControl->getKey(),
            'status' => ControlStatus::Effective,
            'evaluated_at' => now()->subDays(30),
        ]);
    }

    private function outcome(string $code, string $riskDomain, ?string $frequency = null): CanonicalControl
    {
        return CanonicalControl::create([
            'code' => $code,
            'name' => $code.' outcome',
            'category' => $riskDomain,
            'risk_domain' => $riskDomain,
            'priority' => 1,
            'frequency' => $frequency,
        ]);
    }
}
