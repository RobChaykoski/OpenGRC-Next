<?php

namespace Tests\Feature\Console;

use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportFrameworkGapsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prints_a_detailed_gap_report_for_one_framework(): void
    {
        $this->framework();

        $exitCode = Artisan::call('opengrc:evidence:gaps', ['framework' => 'test_framework']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No reviewed control', $output);
        $this->assertStringContainsString('No evidence', $output);
        $this->assertStringContainsString('CTRL-TRAIN-001', $output);
        $this->assertStringContainsString('CTRL-LOG-001', $output);
    }

    #[Test]
    public function json_summary_is_machine_readable(): void
    {
        $this->framework();

        $exitCode = Artisan::call('opengrc:evidence:gaps', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertSame(2, $decoded['test_framework']['outcomes']);
        $this->assertSame(1, $decoded['test_framework']['unreviewed']);
        $this->assertSame(1, $decoded['test_framework']['no_evidence']);
    }

    private function framework(): void
    {
        $unreviewed = CanonicalControl::create([
            'code' => 'CTRL-TRAIN-001',
            'name' => 'Security awareness',
            'priority' => 1,
        ]);
        $noEvidence = CanonicalControl::create([
            'code' => 'CTRL-LOG-001',
            'name' => 'Logging',
            'priority' => 1,
        ]);

        CanonicalFramework::create([
            'code' => 'test_framework',
            'name' => 'Test framework',
            'priority' => 1,
        ])->controls()->attach([$unreviewed->getKey(), $noEvidence->getKey()]);

        Control::factory()->create(['code' => 'L-1'])
            ->canonicalOutcomes()
            ->attach($noEvidence->getKey());
    }
}
