<?php

namespace Tests\Feature;

use App\Models\AssessmentReport;
use App\Models\Finding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsetImportCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_command_can_preview_without_writing(): void
    {
        $this->artisan('opengrc:cset:import', [
            'path' => $this->fixture(),
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, AssessmentReport::query()->count());
        $this->assertSame(0, Finding::query()->count());
    }

    #[Test]
    public function the_command_imports_an_assessment(): void
    {
        $this->artisan('opengrc:cset:import', ['path' => $this->fixture()])
            ->expectsOutputToContain('finding(s) from CSET')
            ->assertSuccessful();

        $this->assertSame(1, AssessmentReport::query()->count());
        $this->assertSame(4, Finding::query()->count());
    }

    #[Test]
    public function the_command_rejects_an_unknown_asset(): void
    {
        $this->artisan('opengrc:cset:import', [
            'path' => $this->fixture(),
            '--asset' => 999999,
        ])->assertFailed();

        $this->assertSame(0, AssessmentReport::query()->count());
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/cset/assessment.json');
    }
}
