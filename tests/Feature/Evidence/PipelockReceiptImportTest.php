<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\Pipelock\PipelockReceiptImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelockReceiptImportTest extends TestCase
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
    public function the_command_imports_a_signed_receipt_and_redacts_the_target(): void
    {
        $envelope = $this->envelope();
        $control = Control::factory()->create(['code' => 'A.8.16']);

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->fixturePath(),
            '--pubkey' => $envelope['signer_key'],
            '--control' => 'A.8.16',
        ])->assertSuccessful();

        $record = EvidenceRecord::query()->sole();
        $source = EvidenceSource::query()->sole();

        $this->assertSame('pipelock:conformance-00000', $record->external_id);
        $this->assertSame(PipelockReceiptImporter::EVIDENCE_TYPE, $record->evidence_type);
        $this->assertSame(ControlStatus::Unknown, $record->status);
        $this->assertSame($control->getKey(), $record->control_id);
        $this->assertSame('pipelock', $record->payload['source']);
        $this->assertSame('allow', $record->payload['receipt']['verdict']);
        $this->assertSame('https://api.example.com', $record->payload['receipt']['target_host']);
        $this->assertSame(hash('sha256', (string) $envelope['action_record']['target']), $record->payload['receipt']['target_sha256']);
        $this->assertTrue($record->payload['verification']['signature']);
        $this->assertTrue($record->payload['verification']['pinned']);
        $this->assertSame('high', $record->payload['verification']['confidence']);
        $this->assertFalse($record->payload['verification']['chain_verified']);

        $encoded = (string) json_encode($record->payload);
        $this->assertStringNotContainsString('/conformance', $encoded);
        $this->assertStringNotContainsString('api.example.com/conformance', $encoded);

        $this->assertSame(EvidenceSourceType::Pipelock, $source->type);
        $this->assertFalse($source->enabled);

        $job = EvidenceJob::query()->sole();
        $this->assertSame(EvidenceJobStatus::Completed, $job->status);
        $this->assertSame(1, $job->records_seen);
        $this->assertSame(1, $job->records_created);
    }

    #[Test]
    public function importing_the_same_receipt_twice_is_a_duplicate(): void
    {
        $envelope = $this->envelope();

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->fixturePath(),
            '--pubkey' => $envelope['signer_key'],
        ])->assertSuccessful();

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->fixturePath(),
            '--pubkey' => $envelope['signer_key'],
        ])->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertSame(1, (int) EvidenceJob::query()->latest('id')->first()?->records_duplicate);
    }

    #[Test]
    public function a_tampered_receipt_is_rejected_without_writing_anything(): void
    {
        $envelope = $this->envelope();
        $envelope['action_record']['verdict'] = 'block';
        $path = $this->temporaryReceipt($envelope);

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $envelope['signer_key'],
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_pinned_key_is_required_unless_the_operator_opts_out(): void
    {
        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->fixturePath(),
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());

        $this->artisan('opengrc:evidence:pipelock', [
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
        $envelope = $this->envelope();

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->fixturePath(),
            '--pubkey' => $envelope['signer_key'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceSource::query()->count());
    }

    #[Test]
    public function an_unknown_control_is_refused_before_import(): void
    {
        $envelope = $this->envelope();

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->fixturePath(),
            '--pubkey' => $envelope['signer_key'],
            '--control' => 'NOPE-1',
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_json_lines_receipt_is_accepted(): void
    {
        $envelope = $this->envelope();
        $path = $this->temporaryJsonLines([$envelope]);

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $envelope['signer_key'],
        ])->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($this->fixturePath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('The Pipelock fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/pipelock/valid-single.json');
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function temporaryReceipt(array $envelope): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pipelock-');

        if ($path === false) {
            $this->fail('Could not create a temporary receipt file.');
        }

        file_put_contents($path, json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>>  $envelopes
     */
    private function temporaryJsonLines(array $envelopes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pipelock-lines-');

        if ($path === false) {
            $this->fail('Could not create a temporary receipt file.');
        }

        $lines = array_map(
            fn (array $envelope): string => (string) json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $envelopes,
        );

        file_put_contents($path, implode("\n", $lines));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
