<?php

namespace Tests\Feature\Console;

use App\Models\AssuranceSnapshot;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SnapshotAssuranceScoresTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_one_snapshot_per_framework_per_day(): void
    {
        $this->frameworkWithLinkedOutcome('test_framework', 'CTRL-ACCESS-001');

        $this->artisan('opengrc:assurance:snapshot')->assertExitCode(0);
        $this->artisan('opengrc:assurance:snapshot')->assertExitCode(0);

        $snapshot = AssuranceSnapshot::query()->sole();

        $this->assertSame('test_framework', $snapshot->framework_code);
        $this->assertSame(now()->toDateString(), $snapshot->snapshot_date->toDateString());
        $this->assertSame(25, $snapshot->score);
        $this->assertSame(1, $snapshot->counts['reviewed']);
        $this->assertArrayHasKey('quality', $snapshot->components);
    }

    #[Test]
    public function it_can_snapshot_one_framework_only(): void
    {
        $this->frameworkWithLinkedOutcome('first_framework', 'CTRL-ACCESS-001');
        $this->frameworkWithLinkedOutcome('second_framework', 'CTRL-LOG-001');

        $this->artisan('opengrc:assurance:snapshot', ['--framework' => 'first_framework'])
            ->assertExitCode(0);

        $this->assertSame(
            ['first_framework'],
            AssuranceSnapshot::query()->pluck('framework_code')->all()
        );
    }

    private function frameworkWithLinkedOutcome(string $frameworkCode, string $outcomeCode): void
    {
        $outcome = CanonicalControl::create([
            'code' => $outcomeCode,
            'name' => $outcomeCode,
            'priority' => 1,
        ]);

        CanonicalFramework::create([
            'code' => $frameworkCode,
            'name' => $frameworkCode,
            'priority' => 1,
        ])->controls()->attach($outcome->getKey());

        $control = Control::factory()->create(['code' => 'C-'.strtoupper($frameworkCode)]);
        $control->canonicalOutcomes()->attach($outcome->getKey());
    }
}
