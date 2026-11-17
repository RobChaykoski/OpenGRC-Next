<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FrameworkAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_passes_a_consistent_framework(): void
    {
        $standardId = $this->createStandard('CSCv8');

        for ($i = 1; $i <= 18; $i++) {
            $this->createControl($standardId, 'CIS-'.$i);
        }

        $this->artisan('opengrc:framework-audit', ['--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_on_duplicate_control_identifiers(): void
    {
        $standardId = $this->createStandard('CSCv8');

        $this->createControl($standardId, 'CIS-1');
        $this->createControl($standardId, 'CIS-1');

        $this->artisan('opengrc:framework-audit', ['--json' => true])
            ->expectsOutputToContain('duplicate control identifier')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_on_an_imported_standard_without_controls(): void
    {
        $this->createStandard('HIPAA-Security');

        $this->artisan('opengrc:framework-audit', ['--json' => true])
            ->expectsOutputToContain('has no controls')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_warns_when_the_control_count_is_unexpected(): void
    {
        $standardId = $this->createStandard('CSCv8');
        $this->createControl($standardId, 'CIS-1');

        $this->artisan('opengrc:framework-audit', ['--json' => true])
            ->expectsOutputToContain('does not match expected')
            ->assertExitCode(0);
    }

    #[Test]
    public function strict_mode_promotes_warnings_to_failures(): void
    {
        $standardId = $this->createStandard('CSCv8');
        $this->createControl($standardId, 'CIS-1');

        $this->artisan('opengrc:framework-audit', ['--json' => true, '--strict' => true])
            ->assertExitCode(1);
    }

    #[Test]
    public function every_catalogued_framework_has_source_data(): void
    {
        foreach (config('framework_catalog.standards') as $code => $definition) {
            $this->assertNotEmpty($definition['licence'] ?? null, "{$code} is missing a licence");
            $this->assertNotEmpty($definition['provenance'] ?? null, "{$code} is missing provenance");
            $this->assertFileExists(base_path($definition['data']), "{$code} source data is missing");
        }
    }

    protected function createStandard(string $code): int
    {
        return DB::table('standards')->insertGetId([
            'name' => "Test framework {$code}",
            'code' => $code,
            'authority' => 'Test',
            'description' => 'Test standard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createControl(int $standardId, string $code): void
    {
        DB::table('controls')->insert([
            'standard_id' => $standardId,
            'code' => $code,
            'title' => "Control {$code}",
            'description' => 'Test control description',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
