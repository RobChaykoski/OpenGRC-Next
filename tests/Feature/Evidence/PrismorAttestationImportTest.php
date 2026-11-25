<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\Prismor\PrismorAttestationImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrismorAttestationImportTest extends TestCase
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
    public function the_command_imports_a_signed_attestation_and_links_it_to_a_control(): void
    {
        $bundle = $this->fixture();
        $control = Control::factory()->create(['code' => 'A.5.15']);

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
            '--pubkey' => $bundle['signing_pubkey'],
            '--control' => 'A.5.15',
        ])->assertSuccessful();

        $record = EvidenceRecord::query()->sole();
        $source = EvidenceSource::query()->sole();

        $this->assertSame('prismor-attestation:dev-arkime-lab:'.$bundle['content_hash'], $record->external_id);
        $this->assertSame(PrismorAttestationImporter::EVIDENCE_TYPE, $record->evidence_type);
        $this->assertSame(ControlStatus::Unknown, $record->status);
        $this->assertSame($control->getKey(), $record->control_id);
        $this->assertSame('prismor.attestation.v1', $record->payload['schema']);
        $this->assertCount(2, $record->payload['attestation']['agents']);
        $this->assertSame('release-bot', $record->payload['attestation']['agents'][0]['name']);
        $this->assertSame(2, $record->payload['attestation']['discovery_summary']['governed']);
        $this->assertSame(2, $record->payload['attestation']['audit_findings']['total']);
        $this->assertSame(1, $record->payload['attestation']['audit_findings']['by_severity']['PASS']);
        $this->assertSame(1, $record->payload['attestation']['audit_findings']['by_category']['egress']);
        $this->assertSame('owasp-llm', $record->payload['attestation']['framework_coverage']['frameworks'][0]['id']);
        $this->assertTrue($record->payload['verification']['content_hash']);
        $this->assertTrue($record->payload['verification']['signature']);
        $this->assertTrue($record->payload['verification']['pinned']);
        $this->assertSame('high', $record->payload['verification']['confidence']);

        $encoded = (string) json_encode($record->payload);
        $this->assertStringNotContainsString('SYNTHETIC_SECRET_VALUE', $encoded);
        $this->assertStringNotContainsString('context7', $encoded);
        $this->assertStringNotContainsString('/home/synthetic', $encoded);
        $this->assertStringNotContainsString('mcp.example.test', $encoded);

        $this->assertSame(EvidenceSourceType::Prismor, $source->type);
        $this->assertFalse($source->enabled);

        $job = EvidenceJob::query()->sole();
        $this->assertSame(EvidenceJobStatus::Completed, $job->status);
        $this->assertSame(1, $job->records_seen);
        $this->assertSame(1, $job->records_created);
    }

    #[Test]
    public function importing_the_same_attestation_twice_is_a_duplicate(): void
    {
        $bundle = $this->fixture();

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
            '--pubkey' => $bundle['signing_pubkey'],
        ])->assertSuccessful();

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
            '--pubkey' => $bundle['signing_pubkey'],
        ])->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertSame(1, (int) EvidenceJob::query()->latest('id')->first()?->records_duplicate);
    }

    #[Test]
    public function a_tampered_attestation_is_rejected_without_writing_anything(): void
    {
        $bundle = $this->fixture();
        $bundle['audit_findings'][0]['message'] = 'changed';
        $path = $this->temporaryBundle($bundle);

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $path,
            '--pubkey' => $this->fixture()['signing_pubkey'],
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_pinned_key_is_required_unless_the_operator_opts_out(): void
    {
        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
            '--allow-unpinned' => true,
        ])->assertSuccessful();

        $record = EvidenceRecord::query()->sole();

        $this->assertFalse($record->payload['verification']['pinned']);
        $this->assertSame('low', $record->payload['verification']['confidence']);
    }

    #[Test]
    public function a_dry_run_verifies_without_writing(): void
    {
        $bundle = $this->fixture();

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
            '--pubkey' => $bundle['signing_pubkey'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceSource::query()->count());
    }

    #[Test]
    public function an_unknown_control_is_refused_before_import(): void
    {
        $bundle = $this->fixture();

        $this->artisan('opengrc:evidence:prismor-attestation', [
            'path' => $this->fixturePath(),
            '--pubkey' => $bundle['signing_pubkey'],
            '--control' => 'NOPE-1',
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
            $this->fail('The attestation fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/prismor/attestation.json');
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    private function temporaryBundle(array $bundle): string
    {
        $path = tempnam(sys_get_temp_dir(), 'prismor-attestation-');

        if ($path === false) {
            $this->fail('Could not create a temporary attestation file.');
        }

        file_put_contents($path, json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
