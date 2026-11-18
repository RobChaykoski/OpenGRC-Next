<?php

namespace Tests\Feature\Console;

use App\Models\CanonicalControl;
use App\Models\Control;
use App\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LinkControlCatalogueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_links_imported_controls_to_canonical_outcomes_with_config_provenance(): void
    {
        [$standard, $control] = $this->importedControl('TEST-STD', 'T-1');
        $outcome = $this->canonicalOutcome('CTRL-ACCESS-001');

        config()->set('canonical_control_links', [
            'TEST-STD' => ['T-1' => ['CTRL-ACCESS-001']],
        ]);

        $this->artisan('opengrc:catalog:link')->assertExitCode(0);

        $this->assertDatabaseHas('control_canonical_outcome', [
            'control_id' => $control->getKey(),
            'canonical_control_id' => $outcome->getKey(),
            'source' => 'config',
        ]);
    }

    #[Test]
    public function running_twice_does_not_duplicate_links(): void
    {
        [, $control] = $this->importedControl('TEST-STD', 'T-1');
        $this->canonicalOutcome('CTRL-ACCESS-001');

        config()->set('canonical_control_links', [
            'TEST-STD' => ['T-1' => ['CTRL-ACCESS-001']],
        ]);

        $this->artisan('opengrc:catalog:link')->assertExitCode(0);
        $this->artisan('opengrc:catalog:link')->assertExitCode(0);

        $this->assertSame(1, DB::table('control_canonical_outcome')->count());
    }

    #[Test]
    public function removing_a_config_entry_removes_its_link_but_keeps_manual_links(): void
    {
        [, $control] = $this->importedControl('TEST-STD', 'T-1');
        $configOutcome = $this->canonicalOutcome('CTRL-ACCESS-001');
        $manualOutcome = $this->canonicalOutcome('CTRL-LOG-001');

        config()->set('canonical_control_links', [
            'TEST-STD' => ['T-1' => ['CTRL-ACCESS-001']],
        ]);

        $this->artisan('opengrc:catalog:link')->assertExitCode(0);

        $control->canonicalOutcomes()->attach($manualOutcome->getKey(), ['source' => 'manual']);

        config()->set('canonical_control_links', [
            'TEST-STD' => [],
        ]);

        $this->artisan('opengrc:catalog:link')->assertExitCode(0);

        $this->assertDatabaseMissing('control_canonical_outcome', [
            'control_id' => $control->getKey(),
            'canonical_control_id' => $configOutcome->getKey(),
        ]);
        $this->assertDatabaseHas('control_canonical_outcome', [
            'control_id' => $control->getKey(),
            'canonical_control_id' => $manualOutcome->getKey(),
            'source' => 'manual',
        ]);
    }

    #[Test]
    public function it_rejects_an_unknown_canonical_outcome_without_writing_anything(): void
    {
        $this->importedControl('TEST-STD', 'T-1');
        $this->canonicalOutcome('CTRL-ACCESS-001');

        config()->set('canonical_control_links', [
            'TEST-STD' => ['T-1' => ['CTRL-NOT-REAL']],
        ]);

        $this->artisan('opengrc:catalog:link')->assertExitCode(1);

        $this->assertSame(0, DB::table('control_canonical_outcome')->count());
    }

    #[Test]
    public function it_reports_a_framework_that_is_not_installed_without_failing(): void
    {
        $this->canonicalOutcome('CTRL-ACCESS-001');

        config()->set('canonical_control_links', [
            'TEST-STD' => ['T-1' => ['CTRL-ACCESS-001']],
        ]);

        $exitCode = Artisan::call('opengrc:catalog:link');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('not installed', $output);
        $this->assertStringContainsString('TEST-STD', $output);
    }

    #[Test]
    public function it_requires_the_canonical_catalogue_to_exist_first(): void
    {
        config()->set('canonical_control_links', [
            'TEST-STD' => ['T-1' => ['CTRL-ACCESS-001']],
        ]);

        $exitCode = Artisan::call('opengrc:catalog:link');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('opengrc:catalog:sync', $output);
    }

    /**
     * @return array{0: Standard, 1: Control}
     */
    private function importedControl(string $standardCode, string $controlCode): array
    {
        $standard = Standard::factory()->create(['code' => $standardCode]);
        $control = Control::factory()->create([
            'standard_id' => $standard->getKey(),
            'code' => $controlCode,
        ]);

        return [$standard, $control];
    }

    private function canonicalOutcome(string $code): CanonicalControl
    {
        return CanonicalControl::create([
            'code' => $code,
            'name' => $code,
            'priority' => 1,
        ]);
    }
}
