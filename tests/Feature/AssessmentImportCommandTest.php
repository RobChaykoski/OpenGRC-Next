<?php

namespace Tests\Feature;

use App\Models\AssessmentReport;
use App\Models\Finding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentImportCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_command_can_preview_without_writing(): void
    {
        $this->artisan('opengrc:assessment:import', [
            'path' => $this->fixture(),
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, AssessmentReport::query()->count());
        $this->assertSame(0, Finding::query()->count());
    }

    #[Test]
    public function the_command_imports_a_report_and_findings(): void
    {
        $this->artisan('opengrc:assessment:import', ['path' => $this->fixture()])
            ->expectsOutputToContain('finding(s)')
            ->assertSuccessful();

        $this->assertSame(1, AssessmentReport::query()->count());
        $this->assertSame(3, Finding::query()->count());
    }

    #[Test]
    public function the_command_rejects_an_unknown_application(): void
    {
        $this->artisan('opengrc:assessment:import', [
            'path' => $this->fixture(),
            '--application' => 999999,
        ])->assertFailed();

        $this->assertSame(0, AssessmentReport::query()->count());
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/sarif/checkov.json');
    }
}
