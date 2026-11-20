<?php

namespace Tests\Feature;

use App\Enums\CrosswalkConfidence;
use App\Enums\UserRole;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Models\Standard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrosswalkExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exports_approved_crosswalks_and_escapes_formula_cells(): void
    {
        [$source, $target, $draftSource, $draftTarget, $sourceStandard, $targetStandard] = $this->data();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        ControlCrosswalk::factory()->approved()->create([
            'source_control_id' => $source->getKey(),
            'target_control_id' => $target->getKey(),
            'rationale' => '=HYPERLINK("https://example.test","click")',
            'confidence' => CrosswalkConfidence::High,
            'reviewed_by' => $admin->getKey(),
        ]);

        ControlCrosswalk::factory()->create([
            'source_control_id' => $draftSource->getKey(),
            'target_control_id' => $draftTarget->getKey(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'crosswalk-');

        $this->artisan('opengrc:crosswalk:export', [
            'source' => $sourceStandard->getKey(),
            'target' => $targetStandard->getKey(),
            '--path' => $path,
        ])->expectsOutputToContain('Exported 1 crosswalk(s)')->assertSuccessful();

        $csv = (string) file_get_contents($path);

        $this->assertStringContainsString('CSC-7', $csv);
        $this->assertStringContainsString('ID.RA-01', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('CSC-8', $csv);

        @unlink($path);
    }

    #[Test]
    public function drafts_are_included_only_when_requested(): void
    {
        [$source, $target, $draftSource, $draftTarget, $sourceStandard, $targetStandard] = $this->data();

        ControlCrosswalk::factory()->create([
            'source_control_id' => $draftSource->getKey(),
            'target_control_id' => $draftTarget->getKey(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'crosswalk-');

        $this->artisan('opengrc:crosswalk:export', [
            'source' => $sourceStandard->getKey(),
            'target' => $targetStandard->getKey(),
            '--path' => $path,
            '--include-drafts' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString('CSC-8', (string) file_get_contents($path));

        @unlink($path);
    }

    #[Test]
    public function it_rejects_the_same_framework_and_unknown_frameworks(): void
    {
        [, , , , $sourceStandard] = $this->data();

        $this->artisan('opengrc:crosswalk:export', [
            'source' => $sourceStandard->getKey(),
            'target' => $sourceStandard->getKey(),
        ])->assertFailed();

        $this->artisan('opengrc:crosswalk:export', [
            'source' => 'DOES-NOT-EXIST',
            'target' => $sourceStandard->getKey(),
        ])->assertFailed();
    }

    /**
     * @return array{0: Control, 1: Control, 2: Control, 3: Control, 4: Standard, 5: Standard}
     */
    private function data(): array
    {
        $sourceStandard = Standard::factory()->create(['code' => 'CSCv8']);
        $targetStandard = Standard::factory()->create(['code' => 'NIST-CSF-2.0']);

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

        return [$source, $target, $draftSource, $draftTarget, $sourceStandard, $targetStandard];
    }
}
