<?php

namespace Tests\Feature\Console;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSeverity;
use App\Enums\EvidenceSourceType;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Models\Finding;
use App\Models\Implementation;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class BuildEvidencePackTest extends TestCase
{
    use RefreshDatabase;

    private string $output = '';

    protected function tearDown(): void
    {
        if ($this->output !== '' && File::exists($this->output)) {
            File::delete($this->output);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_builds_a_zip_with_an_index_hashes_validation_results_and_findings(): void
    {
        ['control' => $control, 'payload' => $payload] = $this->frameworkWithEvidence();

        $this->output = storage_path('app/exports/test-pack-'.uniqid().'.zip');

        $this->artisan('opengrc:evidence:pack', [
            'framework' => 'pack_framework',
            '--output' => $this->output,
        ])->assertExitCode(0);

        $this->assertFileExists($this->output);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->output) === true);

        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $this->assertEqualsCanonicalizing([
            'manifest.json',
            'README.txt',
            'outcomes.csv',
            'controls.csv',
            'implementations.csv',
            'evidence.csv',
            'validations.csv',
            'findings.csv',
        ], $names);

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        $this->assertSame('pack_framework', $manifest['framework']['code']);
        $this->assertSame(1, $manifest['counts']['outcomes']);
        $this->assertSame(1, $manifest['counts']['control_links']);
        $this->assertSame(1, $manifest['counts']['implementations']);
        $this->assertSame(1, $manifest['counts']['evidence_records']);
        $this->assertSame(1, $manifest['counts']['validation_results']);
        $this->assertSame(1, $manifest['counts']['findings']);

        $evidenceCsv = (string) $zip->getFromName('evidence.csv');
        $this->assertStringContainsString($control->code, $evidenceCsv);
        $this->assertStringContainsString('host-1', $evidenceCsv);

        $all = '';

        foreach ($names as $name) {
            $all .= (string) $zip->getFromName($name);
        }

        $zip->close();

        // Raw payload content must never leave the ledger through a pack.
        $this->assertStringNotContainsString($payload, $all);
        $this->assertStringNotContainsString('SUPER-SECRET-VALUE', $all);
    }

    #[Test]
    public function an_unknown_framework_fails_without_writing_a_file(): void
    {
        $this->output = storage_path('app/exports/test-pack-'.uniqid().'.zip');

        $this->artisan('opengrc:evidence:pack', [
            'framework' => 'missing_framework',
            '--output' => $this->output,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->output);
    }

    /**
     * @return array{control: Control, payload: string}
     */
    private function frameworkWithEvidence(): array
    {
        $outcome = CanonicalControl::create([
            'code' => 'CTRL-ACCESS-001',
            'name' => 'Least-privilege access control',
            'priority' => 1,
            'frequency' => 'quarterly',
        ]);

        CanonicalFramework::create([
            'code' => 'pack_framework',
            'name' => 'Pack framework',
            'priority' => 1,
        ])->controls()->attach($outcome->getKey());

        $control = Control::factory()->create(['code' => 'AC-2']);
        $control->canonicalOutcomes()->attach($outcome->getKey());

        $implementation = Implementation::factory()->create(['code' => 'IMP-1']);
        $control->implementations()->attach($implementation->getKey());

        $source = EvidenceSource::create([
            'name' => 'Test source',
            'type' => EvidenceSourceType::Other,
            'enabled' => true,
            'configuration' => [],
        ]);

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        $payload = 'SUPER-SECRET-VALUE';

        app(EvidenceIngestor::class)->ingest(
            $source,
            $job,
            new NormalisedEvidence(
                externalId: 'host-1',
                evidenceType: 'test',
                status: ControlStatus::Effective,
                collectedAt: CarbonImmutable::parse('2026-11-18T09:00:00Z'),
                payload: ['matched' => $payload],
                controlIdentifier: 'AC-2',
            ),
        );

        ControlValidation::create([
            'control_id' => $control->getKey(),
            'status' => ControlStatus::Effective,
            'evaluated_at' => now(),
        ]);

        Finding::create([
            'title' => 'MFA gap on one host',
            'description' => 'A host is not enrolled in MFA.',
            'severity' => EvidenceSeverity::High,
            'control_id' => $control->getKey(),
            'identified_at' => now(),
        ]);

        return ['control' => $control, 'payload' => $payload];
    }
}
