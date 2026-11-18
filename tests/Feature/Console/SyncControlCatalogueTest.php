<?php

namespace Tests\Feature\Console;

use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncControlCatalogueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_canonical_controls_and_framework_mappings_from_config(): void
    {
        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        $catalogue = config('framework_scoping.controls');
        $frameworks = config('framework_scoping.frameworks');

        $this->assertSame(count($catalogue), CanonicalControl::query()->count());
        $this->assertSame(count($frameworks), CanonicalFramework::query()->count());

        $mfa = CanonicalControl::query()->where('code', 'CTRL-ACCESS-002')->sole();

        $this->assertSame('Multi-factor authentication', $mfa->name);
        $this->assertSame('Identity', $mfa->category);
        $this->assertSame('high', $mfa->automation_level);
        $this->assertSame('weekly', $mfa->frequency);
        $this->assertContains('Microsoft Entra ID', $mfa->sources);

        // A control is one row shared by every framework that requires it.
        $this->assertEqualsCanonicalizing(
            ['cmmc', 'pci_dss', 'soc2', 'cis_v8', 'nist_800_53'],
            $mfa->frameworks()->pluck('code')->all()
        );
    }

    #[Test]
    public function running_twice_creates_no_duplicates_and_no_extra_mappings(): void
    {
        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        $controlCount = CanonicalControl::query()->count();
        $frameworkCount = CanonicalFramework::query()->count();
        $mappingCount = $this->mappingCount();

        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        $this->assertSame($controlCount, CanonicalControl::query()->count());
        $this->assertSame($frameworkCount, CanonicalFramework::query()->count());
        $this->assertSame($mappingCount, $this->mappingCount());
        $this->assertSame($controlCount, CanonicalControl::query()->distinct()->count('code'));
    }

    #[Test]
    public function it_applies_definition_changes_from_config(): void
    {
        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        config()->set('framework_scoping.controls.CTRL-GOV-001.name', 'Governance, policy and oversight');

        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        $this->assertSame(
            'Governance, policy and oversight',
            CanonicalControl::query()->where('code', 'CTRL-GOV-001')->value('name')
        );
    }

    #[Test]
    public function it_removes_mappings_that_config_no_longer_declares(): void
    {
        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        $nist80053 = CanonicalFramework::query()->where('code', 'nist_800_53')->sole();
        $this->assertTrue($nist80053->controls()->where('code', 'CTRL-ACCESS-003')->exists());

        config()->set('framework_scoping.frameworks.nist_800_53.controls', ['CTRL-GOV-001', 'CTRL-RISK-001']);

        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        $this->assertEqualsCanonicalizing(
            ['CTRL-GOV-001', 'CTRL-RISK-001'],
            $nist80053->controls()->pluck('code')->all()
        );

        // Detaching a mapping must not delete the canonical control itself.
        $this->assertTrue(CanonicalControl::query()->where('code', 'CTRL-ACCESS-003')->exists());
    }

    #[Test]
    public function it_rejects_unknown_control_references_without_writing_anything(): void
    {
        config()->set('framework_scoping.frameworks.nist_csf.controls', ['CTRL-GOV-001', 'CTRL-NOT-REAL']);

        $this->artisan('opengrc:catalog:sync')->assertExitCode(1);

        $this->assertSame(0, CanonicalControl::query()->count());
        $this->assertSame(0, CanonicalFramework::query()->count());
    }

    #[Test]
    public function it_keeps_and_reports_controls_that_are_no_longer_in_config(): void
    {
        $this->artisan('opengrc:catalog:sync')->assertExitCode(0);

        CanonicalControl::create([
            'code' => 'CTRL-LEGACY-001',
            'name' => 'Retired outcome',
            'priority' => 2,
        ]);

        $exitCode = Artisan::call('opengrc:catalog:sync');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertTrue(CanonicalControl::query()->where('code', 'CTRL-LEGACY-001')->exists());
        $this->assertStringContainsString('CTRL-LEGACY-001', $output);
        $this->assertStringContainsString('no longer in config', $output);
    }

    private function mappingCount(): int
    {
        return CanonicalFramework::query()
            ->withCount('controls')
            ->get()
            ->sum('controls_count');
    }
}
