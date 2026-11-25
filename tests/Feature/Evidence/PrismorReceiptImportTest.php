<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\Prismor\PrismorReceiptImporter;
use App\Services\Evidence\Prismor\PrismorReceiptVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrismorReceiptImportTest extends TestCase
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
    public function the_command_imports_a_signed_receipt_and_links_it_to_a_control(): void
    {
        $receipt = $this->fixture();
        $control = Control::factory()->create(['code' => 'A.5.15']);

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
            '--pubkey' => $receipt['signing_pubkey'],
            '--control' => 'A.5.15',
        ])->assertSuccessful();

        $record = EvidenceRecord::query()->sole();
        $source = EvidenceSource::query()->sole();

        $this->assertSame('prismor:dev-4a1e:evt-9f2c', $record->external_id);
        $this->assertSame(PrismorReceiptImporter::EVIDENCE_TYPE, $record->evidence_type);
        $this->assertSame(ControlStatus::Unknown, $record->status);
        $this->assertSame($control->getKey(), $record->control_id);
        $this->assertSame('blocked', $record->payload['receipt']['verdict']);
        $this->assertSame('egress-deny', $record->payload['receipt']['rule_id']);
        $this->assertSame('Bash', $record->payload['receipt']['tool_name']);
        $this->assertTrue($record->payload['verification']['integrity']);
        $this->assertTrue($record->payload['verification']['signature']);
        $this->assertTrue($record->payload['verification']['pinned']);
        $this->assertSame('high', $record->payload['verification']['confidence']);
        $this->assertArrayNotHasKey('input', $record->payload['receipt']);
        $this->assertArrayNotHasKey('command', $record->payload['receipt']);

        $this->assertSame(EvidenceSourceType::Prismor, $source->type);
        $this->assertFalse($source->enabled);

        $job = EvidenceJob::query()->sole();
        $this->assertSame(EvidenceJobStatus::Completed, $job->status);
        $this->assertSame(1, $job->records_seen);
        $this->assertSame(1, $job->records_created);
        $this->assertSame(0, $job->records_duplicate);
    }

    #[Test]
    public function importing_the_same_receipt_twice_is_a_duplicate_not_a_new_record(): void
    {
        $receipt = $this->fixture();

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
            '--pubkey' => $receipt['signing_pubkey'],
        ])->assertSuccessful();

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
            '--pubkey' => $receipt['signing_pubkey'],
        ])->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertSame(1, (int) EvidenceJob::query()->latest('id')->first()?->records_duplicate);
    }

    #[Test]
    public function a_public_key_file_can_pin_the_signer(): void
    {
        $receipt = $this->fixture();
        $path = tempnam(sys_get_temp_dir(), 'prismor-key-');

        if ($path === false) {
            $this->fail('Could not create a temporary key file.');
        }

        file_put_contents($path, $receipt['signing_pubkey']);
        $this->temporaryFiles[] = $path;

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
            '--pubkey-file' => $path,
        ])->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_tampered_receipt_is_rejected_without_writing_anything(): void
    {
        $receipt = $this->fixture();
        $path = $this->temporaryReceipt(['verdict' => 'allowed']);

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $path,
            '--pubkey' => $receipt['signing_pubkey'],
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_pinned_key_is_required_unless_the_operator_opts_out(): void
    {
        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());

        $this->artisan('opengrc:evidence:prismor', [
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
        $receipt = $this->fixture();

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
            '--pubkey' => $receipt['signing_pubkey'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceSource::query()->count());
    }

    #[Test]
    public function an_unknown_control_is_refused_before_import(): void
    {
        $receipt = $this->fixture();

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $this->fixturePath(),
            '--pubkey' => $receipt['signing_pubkey'],
            '--control' => 'NOPE-1',
        ])->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_sequence_gap_is_reported_but_does_not_reject_the_batch(): void
    {
        $pair = $this->keypair();
        $genesis = str_repeat('0', 64);
        $first = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-gap-0']);
        $second = $this->signedReceipt($pair, 2, $this->hashOf($first), ['event_id' => 'evt-gap-2']);

        $result = app(PrismorReceiptImporter::class)->import(
            [$first, $second],
            'Prismor',
            null,
            null,
            base64_encode($pair['public']),
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotEmpty(array_filter($result['warnings'], fn (string $warning): bool => str_contains($warning, 'sequence gap')));
    }

    #[Test]
    public function a_broken_chain_link_rejects_the_batch(): void
    {
        $pair = $this->keypair();
        $genesis = str_repeat('0', 64);
        $first = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-broken-0']);
        $second = $this->signedReceipt($pair, 1, $genesis, ['event_id' => 'evt-broken-1']);

        $result = app(PrismorReceiptImporter::class)->import(
            [$first, $second],
            'Prismor',
            null,
            null,
            base64_encode($pair['public']),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['failed']);
        $this->assertNotEmpty(array_filter($result['errors'], fn (string $error): bool => str_contains($error, 'broken chain link')));
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function duplicate_sequence_numbers_reject_the_batch(): void
    {
        $pair = $this->keypair();
        $genesis = str_repeat('0', 64);
        $first = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-dup-a']);
        $second = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-dup-b']);

        $result = app(PrismorReceiptImporter::class)->import(
            [$first, $second],
            'Prismor',
            null,
            null,
            base64_encode($pair['public']),
        );

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty(array_filter($result['errors'], fn (string $error): bool => str_contains($error, 'more than one receipt at seq 0')));
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function duplicate_identities_in_one_batch_are_rejected(): void
    {
        $pair = $this->keypair();
        $genesis = str_repeat('0', 64);
        $first = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-same']);
        $second = $this->signedReceipt($pair, 1, $this->hashOf($first), [
            'event_id' => 'evt-same',
            'ts' => '2026-08-16T10:05:11.512834+00:00',
        ]);

        $result = app(PrismorReceiptImporter::class)->import(
            [$first, $second],
            'Prismor',
            null,
            null,
            base64_encode($pair['public']),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['failed']);
        $this->assertNotEmpty(array_filter($result['errors'], fn (string $error): bool => str_contains($error, 'appears more than once')));
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_json_lines_file_imports_each_receipt(): void
    {
        $pair = $this->keypair();
        $genesis = str_repeat('0', 64);
        $first = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-lines-0']);
        $second = $this->signedReceipt($pair, 1, $this->hashOf($first), ['event_id' => 'evt-lines-1']);
        $path = $this->temporaryJsonLines([$first, $second]);

        $this->artisan('opengrc:evidence:prismor', [
            'path' => $path,
            '--pubkey' => base64_encode($pair['public']),
        ])->assertSuccessful();

        $this->assertSame(2, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_resigned_receipt_with_the_same_identity_is_a_conflict(): void
    {
        $pair = $this->keypair();
        $genesis = str_repeat('0', 64);
        $first = $this->signedReceipt($pair, 0, $genesis, ['event_id' => 'evt-resign']);
        $resigned = $this->signedReceipt($pair, 0, $genesis, [
            'event_id' => 'evt-resign',
            'ts' => '2026-08-16T10:05:11.512834+00:00',
        ]);
        $pubkey = base64_encode($pair['public']);

        $initial = app(PrismorReceiptImporter::class)->import([$first], 'Prismor', null, null, $pubkey);
        $conflict = app(PrismorReceiptImporter::class)->import([$resigned], 'Prismor', null, null, $pubkey);

        $this->assertSame(1, $initial['created']);
        $this->assertSame(0, $conflict['created']);
        $this->assertSame(1, $conflict['failed']);
        $this->assertNotEmpty(array_filter($conflict['errors'], fn (string $error): bool => str_contains($error, 'different bytes')));
        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($this->fixturePath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('The Prismor fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/prismor/receipt.json');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function temporaryReceipt(array $overrides): string
    {
        $path = tempnam(sys_get_temp_dir(), 'prismor-');

        if ($path === false) {
            $this->fail('Could not create a temporary receipt file.');
        }

        file_put_contents($path, json_encode(array_merge($this->fixture(), $overrides), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     */
    private function temporaryJsonLines(array $receipts): string
    {
        $path = tempnam(sys_get_temp_dir(), 'prismor-lines-');

        if ($path === false) {
            $this->fail('Could not create a temporary receipt file.');
        }

        $lines = array_map(
            fn (array $receipt): string => json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $receipts,
        );

        file_put_contents($path, implode("\n", $lines));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @return array{public: string, secret: string}
     */
    private function keypair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'public' => sodium_crypto_sign_publickey($pair),
            'secret' => sodium_crypto_sign_secretkey($pair),
        ];
    }

    /**
     * @param  array{public: string, secret: string}  $pair
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedReceipt(array $pair, int $seq, string $prevHash, array $overrides = []): array
    {
        $fields = array_merge([
            'event_id' => 'evt-'.$seq,
            'verdict' => 'blocked',
            'severity' => 'high',
            'rule_id' => 'egress-deny',
            'tool_name' => 'Bash',
            'evidence_hash' => str_repeat('a', 64),
            'session_id' => 'sess-1',
            'ts' => '2026-08-16T10:04:11.512834+00:00',
            'device_id' => 'dev-test',
            'agent' => 'claude-code',
            'agent_name' => 'release-bot',
            'subagent_id' => null,
            'subject' => ['user_id' => 'u-1', 'team_id' => 't-1', 'org_id' => 'o-1'],
            'seq' => $seq,
            'prev_hash' => $prevHash,
        ], $overrides);

        $subject = is_array($fields['subject']) ? $fields['subject'] : [];

        $hash = hash('sha256', PrismorReceiptVerifier::canonical([
            'event_id' => $fields['event_id'],
            'verdict' => $fields['verdict'],
            'severity' => $fields['severity'],
            'rule_id' => $fields['rule_id'],
            'tool_name' => $fields['tool_name'],
            'evidence_hash' => $fields['evidence_hash'],
            'session_id' => $fields['session_id'],
            'seq' => $fields['seq'],
            'prev_hash' => $fields['prev_hash'],
        ]));

        $signature = sodium_crypto_sign_detached(
            PrismorReceiptVerifier::canonical([
                'hash' => $hash,
                'ts' => $fields['ts'],
                'identity' => [
                    'device_id' => $fields['device_id'],
                    'agent' => $fields['agent'],
                    'agent_name' => $fields['agent_name'],
                    'subagent_id' => $fields['subagent_id'],
                    'user_id' => $subject['user_id'] ?? null,
                    'team_id' => $subject['team_id'] ?? null,
                    'org_id' => $subject['org_id'] ?? null,
                ],
            ]),
            $pair['secret'],
        );

        return $fields + [
            'hash' => $hash,
            'signature' => base64_encode($signature),
            'signing_alg' => 'ed25519',
            'signing_pubkey' => base64_encode($pair['public']),
            'signing_key_id' => substr(hash('sha256', $pair['public']), 0, 16),
        ];
    }

    /**
     * @param  array<string, mixed>  $receipt
     */
    private function hashOf(array $receipt): string
    {
        return (string) $receipt['hash'];
    }
}
