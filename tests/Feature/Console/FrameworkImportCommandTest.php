<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FrameworkImportCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_the_catalog_when_no_code_is_given(): void
    {
        $this->artisan('opengrc:framework-import')
            ->expectsOutputToContain('CSCv8')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_rejects_an_unknown_framework_code(): void
    {
        $this->artisan('opengrc:framework-import', ['code' => 'NOT-A-FRAMEWORK'])
            ->expectsOutputToContain('Unknown framework')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_imports_a_framework_by_code(): void
    {
        $this->artisan('opengrc:framework-import', ['code' => 'CSCv8'])
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('standards')->where('code', 'CSCv8')->count());
        $this->assertSame(18, DB::table('controls')->count());
    }
}
