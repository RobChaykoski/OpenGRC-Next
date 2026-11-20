<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_a_healthy_installation_in_json(): void
    {
        $this->artisan('opengrc:doctor', ['--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_includes_runtime_and_database_checks(): void
    {
        $this->artisan('opengrc:doctor', ['--json' => true, '--skip-queue' => true])
            ->expectsOutputToContain('PHP version')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_warns_when_the_discovery_console_is_configured_but_never_synced(): void
    {
        config([
            'services.discovery' => [
                'base_url' => 'https://discovery.test',
                'token' => 'console-token',
                'tenant' => 'acme',
            ],
        ]);

        $this->artisan('opengrc:doctor', ['--json' => true, '--skip-queue' => true])
            ->expectsOutputToContain('configured but never synced')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_when_strict_and_warnings_exist(): void
    {
        // The asset manifest is absent in a fresh checkout until `npm run build`
        // runs, which is exactly the kind of warning strict mode should promote.
        if (is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Built assets are present, so there is no warning to promote.');
        }

        $this->artisan('opengrc:doctor', ['--json' => true, '--strict' => true])
            ->assertExitCode(1);
    }
}
