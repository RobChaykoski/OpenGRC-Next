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
use Tests\Support\PipelockChain;
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
        $this->assertTrue($record->payload['verification']['chain_verified']);
        $this->assertSame('genesis', $record->payload['verification']['chain_scope']);
        $this->assertSame('genesis', $record->payload['receipt']['chain_prev_hash']);
        $this->assertSame('9994a67b9cb324bae8d648b5baccd5d5403600b2bc5047a40d0355a610f2dc44', $record->payload['receipt']['chain_hash']);

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

    #[Test]
    public function the_published_chain_is_imported_and_marked_chain_verified(): void
    {
        $envelopes = $this->chainEnvelopes();

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->chainPath(),
            '--pubkey' => $envelopes[0]['signer_key'],
        ])->assertSuccessful();

        $records = EvidenceRecord::query()->orderBy('id')->get();

        $this->assertCount(5, $records);

        foreach ($records as $index => $record) {
            $this->assertSame($index, $record->payload['receipt']['chain_seq']);
            $this->assertTrue($record->payload['verification']['chain_verified']);
            $this->assertSame('genesis', $record->payload['verification']['chain_scope']);
        }

        $this->assertSame(
            $records[1]->payload['receipt']['chain_prev_hash'],
            $records[0]->payload['receipt']['chain_hash'],
        );
    }

    #[Test]
    public function a_broken_chain_is_rejected_without_writing_anything(): void
    {
        $envelopes = $this->chainEnvelopes('broken-chain');

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $this->chainPath('broken-chain'),
            '--pubkey' => $envelopes[0]['signer_key'],
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceJob::query()->count());
    }

    #[Test]
    public function a_mid_chain_slice_needs_the_opt_in_and_stays_unverified(): void
    {
        $envelopes = array_slice($this->chainEnvelopes(), 3);
        $path = $this->temporaryJsonLines([$envelopes[0], $envelopes[1]]);
        $key = $envelopes[0]['signer_key'];

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $key,
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $key,
            '--allow-partial-chain' => true,
        ])->assertSuccessful();

        $records = EvidenceRecord::query()->orderBy('id')->get();

        $this->assertCount(2, $records);

        foreach ($records as $record) {
            $this->assertFalse($record->payload['verification']['chain_verified']);
            $this->assertSame('partial', $record->payload['verification']['chain_scope']);
        }

        $this->assertSame(EvidenceJobStatus::Completed, EvidenceJob::query()->sole()->status);
    }

    #[Test]
    public function an_out_of_order_chain_is_rejected(): void
    {
        $envelopes = $this->chainEnvelopes();
        $path = $this->temporaryJsonLines([$envelopes[0], $envelopes[2], $envelopes[1]]);

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $envelopes[0]['signer_key'],
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_rotated_chain_is_imported_when_every_segment_key_is_pinned(): void
    {
        $chain = PipelockChain::rotated();
        $path = $this->temporaryJsonLines($chain['envelopes']);

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $chain['keys']['a'].','.$chain['keys']['b'],
        ])->assertSuccessful();

        $records = EvidenceRecord::query()->orderBy('id')->get();

        $this->assertCount(5, $records);

        foreach ($records as $record) {
            $this->assertTrue($record->payload['verification']['chain_verified']);
            $this->assertSame('genesis', $record->payload['verification']['chain_scope']);
        }

        $this->assertSame(
            [0, 1, 2, 0, 1],
            $records->map(fn (EvidenceRecord $record): int => $record->payload['receipt']['chain_seq'])->all(),
        );
        $this->assertSame($chain['keys']['b'], $records[3]->payload['receipt']['signer_key']);
        $this->assertSame(
            $records[3]->payload['receipt']['chain_prev_hash'],
            $records[2]->payload['receipt']['chain_hash'],
        );
    }

    #[Test]
    public function a_rotated_chain_without_the_second_key_pinned_is_refused(): void
    {
        $chain = PipelockChain::rotated();
        $path = $this->temporaryJsonLines($chain['envelopes']);

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey' => $chain['keys']['a'],
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_pubkey_file_may_pin_every_segment_key(): void
    {
        $chain = PipelockChain::rotated();
        $path = $this->temporaryJsonLines($chain['envelopes']);
        $keyFile = tempnam(sys_get_temp_dir(), 'pipelock-keys-');

        if ($keyFile === false) {
            $this->fail('Could not create a temporary key file.');
        }

        file_put_contents($keyFile, $chain['keys']['a']."\n".$chain['keys']['b']."\n");
        $this->temporaryFiles[] = $keyFile;

        $this->artisan('opengrc:evidence:pipelock', [
            'path' => $path,
            '--pubkey-file' => $keyFile,
        ])->assertSuccessful();

        $this->assertSame(5, EvidenceRecord::query()->count());
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

    private function chainPath(string $name = 'valid-chain'): string
    {
        return base_path("tests/Fixtures/pipelock/{$name}.jsonl");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function chainEnvelopes(string $name = 'valid-chain'): array
    {
        $contents = (string) file_get_contents($this->chainPath($name));
        $envelopes = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $this->fail('The Pipelock chain fixture is not valid JSONL: '.$exception->getMessage());
            }

            if (is_array($decoded)) {
                $envelopes[] = $decoded;
            }
        }

        return $envelopes;
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
