<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\Sensor\SensorSummaryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SensorSummaryImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    #[Test]
    public function the_command_imports_a_hash_verified_summary_and_links_it_to_a_control(): void
    {
        $control = Control::factory()->create(['code' => 'A.8.16']);

        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--sha256' => $this->fixtureHash(),
            '--control' => 'A.8.16',
        ])->assertSuccessful();

        $record = EvidenceRecord::query()->sole();
        $source = EvidenceSource::query()->sole();

        $this->assertStringStartsWith('sensor-summary:suricata:edge-01:', $record->external_id);
        $this->assertSame(SensorSummaryImporter::EVIDENCE_TYPE, $record->evidence_type);
        $this->assertSame(ControlStatus::Unknown, $record->status);
        $this->assertSame($control->getKey(), $record->control_id);
        $this->assertSame('suricata', $record->payload['sensor_source']);
        $this->assertSame('edge-01', $record->payload['sensor']);
        $this->assertSame(1200, $record->payload['counts']['events']);
        $this->assertSame(3, $record->payload['counts']['blocked']);
        $this->assertSame(3, $record->payload['by_verdict']['blocked']);
        $this->assertSame('https', $record->payload['by_service'][0]['name']);
        $this->assertSame('dmz', $record->payload['edges'][0]['from']);
        $this->assertTrue($record->payload['health']['healthy']);
        $this->assertTrue($record->payload['verification']['hash_verified']);
        $this->assertSame('medium', $record->payload['verification']['confidence']);
        $this->assertSame($this->fixtureHash(), $record->payload['verification']['file_sha256']);

        $encoded = (string) json_encode($record->payload);
        $this->assertStringNotContainsString('must not be stored', $encoded);
        $this->assertStringNotContainsString('must-not-be-stored', $encoded);

        $this->assertSame(EvidenceSourceType::Sensor, $source->type);
        $this->assertFalse($source->enabled);

        $job = EvidenceJob::query()->sole();
        $this->assertSame(EvidenceJobStatus::Completed, $job->status);
        $this->assertSame(1, $job->records_seen);
        $this->assertSame(1, $job->records_created);
    }

    #[Test]
    public function importing_the_same_summary_twice_is_a_duplicate(): void
    {
        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--sha256' => $this->fixtureHash(),
        ])->assertSuccessful();

        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--sha256' => $this->fixtureHash(),
        ])->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertSame(1, (int) EvidenceJob::query()->latest('id')->first()?->records_duplicate);
    }

    #[Test]
    public function a_wrong_file_hash_is_rejected_without_writing_anything(): void
    {
        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--sha256' => str_repeat('a', 64),
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function an_unhashed_summary_requires_an_explicit_opt_out(): void
    {
        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());

        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--allow-unhashed' => true,
        ])->assertSuccessful();

        $record = EvidenceRecord::query()->sole();

        $this->assertFalse($record->payload['verification']['hash_verified']);
        $this->assertSame('low', $record->payload['verification']['confidence']);
    }

    #[Test]
    public function a_dry_run_validates_without_writing(): void
    {
        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--sha256' => $this->fixtureHash(),
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceSource::query()->count());
    }

    #[Test]
    public function an_unknown_control_is_refused_before_import(): void
    {
        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $this->fixturePath(),
            '--sha256' => $this->fixtureHash(),
            '--control' => 'NOPE-1',
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_wrong_schema_is_rejected(): void
    {
        $summary = $this->fixture();
        $summary['schema'] = 'opengrc.sensor-summary.v2';
        $path = $this->temporarySummary($summary);

        $this->artisan('opengrc:evidence:sensor-summary', [
            'path' => $path,
            '--sha256' => hash_file('sha256', $path),
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($this->fixturePath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('The sensor fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/sensor/suricata-summary.json');
    }

    private function fixtureHash(): string
    {
        return hash_file('sha256', $this->fixturePath());
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function temporarySummary(array $summary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sensor-summary-');

        if ($path === false) {
            $this->fail('Could not create a temporary summary file.');
        }

        file_put_contents($path, json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
