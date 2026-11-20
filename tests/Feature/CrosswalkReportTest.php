<?php

namespace Tests\Feature;

use App\Enums\CrosswalkConfidence;
use App\Enums\UserRole;
use App\Filament\Pages\CrosswalkReport;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Models\Standard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrosswalkReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_approved_crosswalks_and_excludes_drafts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sourceStandard = Standard::factory()->create(['code' => 'CSCv8', 'name' => 'CIS Controls']);
        $targetStandard = Standard::factory()->create(['code' => 'NIST-CSF-2.0', 'name' => 'NIST CSF']);

        $source = Control::factory()->create([
            'standard_id' => $sourceStandard->getKey(),
            'code' => 'CSC-7',
            'title' => 'Continuous vulnerability management',
        ]);
        $target = Control::factory()->create([
            'standard_id' => $targetStandard->getKey(),
            'code' => 'ID.RA-01',
            'title' => 'Vulnerabilities are identified',
        ]);

        $draftSource = Control::factory()->create([
            'standard_id' => $sourceStandard->getKey(),
            'code' => 'CSC-8',
            'title' => 'Audit log management',
        ]);
        $draftTarget = Control::factory()->create([
            'standard_id' => $targetStandard->getKey(),
            'code' => 'DE.AE-03',
            'title' => 'Event data is collected',
        ]);

        ControlCrosswalk::factory()->approved()->create([
            'source_control_id' => $source->getKey(),
            'target_control_id' => $target->getKey(),
            'confidence' => CrosswalkConfidence::High,
            'reviewed_by' => $admin->getKey(),
        ]);

        ControlCrosswalk::factory()->create([
            'source_control_id' => $draftSource->getKey(),
            'target_control_id' => $draftTarget->getKey(),
            'confidence' => CrosswalkConfidence::Medium,
        ]);

        $this->actingAs($viewer)
            ->get(CrosswalkReport::getUrl([
                'source' => $sourceStandard->getKey(),
                'target' => $targetStandard->getKey(),
            ]))
            ->assertOk()
            ->assertSee('CSC-7')
            ->assertSee('ID.RA-01')
            ->assertSee('High')
            ->assertSee('Approved mappings')
            ->assertSee('Draft mappings')
            ->assertSee('Export CSV')
            ->assertDontSee('CSC-8')
            ->assertDontSee('DE.AE-03');
    }
}
