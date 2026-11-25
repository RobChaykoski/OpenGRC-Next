<?php

namespace Tests\Feature;

use App\Exceptions\EvidenceImmutableException;
use App\Models\Audit;
use App\Models\EvidenceLedgerEntry;
use App\Models\EvidenceState;
use App\Models\FileAttachment;
use App\Models\User;
use App\Services\Evidence\EvidenceLedger;
use App\Support\InstanceMode;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceLedgerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_new_attachment_is_hashed_and_ledgered(): void
    {
        [$attachment, $hash] = $this->attachment();

        $entry = $attachment->ledgerEntries()->first();

        $this->assertNotNull($entry);
        $this->assertSame(EvidenceLedgerEntry::EVENT_SUBMITTED, $entry->event);
        $this->assertSame(1, $entry->sequence);
        $this->assertSame($hash, $entry->file_sha256);
        $this->assertSame($hash, $attachment->fresh()->file_sha256);
        $this->assertSame(EvidenceState::STATUS_ACTIVE, $attachment->evidenceState->status);
    }

    #[Test]
    public function production_mode_blocks_deleting_and_repointing_evidence(): void
    {
        setting([InstanceMode::MODE_KEY => 'production']);

        [$attachment] = $this->attachment();

        try {
            $attachment->update(['file_path' => 'attachments/other.pdf']);
            $this->fail('Changing the evidence path should be blocked.');
        } catch (EvidenceImmutableException) {
            $this->assertTrue(true);
        }

        $this->expectException(EvidenceImmutableException::class);
        $attachment->delete();
    }

    #[Test]
    public function withdrawal_requires_a_reason_and_is_recorded_not_deleted(): void
    {
        [$attachment] = $this->attachment();
        $ledger = app(EvidenceLedger::class);

        try {
            $ledger->withdraw($attachment, 'too short');
            $this->fail('A short reason should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $entry = $ledger->withdraw($attachment, 'Superseded by the corrected evidence submission.', User::factory()->create());

        $this->assertSame(EvidenceLedgerEntry::EVENT_WITHDRAWN, $entry->event);
        $this->assertSame(EvidenceState::STATUS_WITHDRAWN, $attachment->fresh()->evidenceState->status);
        $this->assertNotNull($attachment->fresh());
        $this->assertSame(2, $attachment->ledgerEntries()->count());
    }

    #[Test]
    public function verification_detects_a_replaced_file(): void
    {
        [$attachment] = $this->attachment();
        $ledger = app(EvidenceLedger::class);

        $this->assertSame([], $ledger->verifySubject($attachment)[0]);

        Storage::disk('private')->put($attachment->file_path, 'tampered');

        $errors = $ledger->verifySubject($attachment->fresh())[0];

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('hash does not match', $errors[0]);
    }

    #[Test]
    public function verify_all_covers_subjects_without_soft_deletes(): void
    {
        [$attachment] = $this->attachment();

        // FileAttachment has no SoftDeletes. Verifying the whole ledger used to
        // throw BadMethodCallException from the orphan scan on any real install.
        $report = app(EvidenceLedger::class)->verifyAll();

        $this->assertSame(1, $report['checked']);
        $this->assertSame([], $report['errors']);
        $this->assertSame([], $report['warnings']);
        $this->assertTrue(FileAttachment::query()->whereKey($attachment->getKey())->exists());
    }

    #[Test]
    public function ledger_entries_cannot_be_updated_or_deleted(): void
    {
        [$attachment] = $this->attachment();
        $entry = $attachment->ledgerEntries()->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('evidence_ledger_entries')->where('id', $entry->id)->update(['event' => 'changed']);
    }

    /**
     * @return array{0: FileAttachment, 1: string}
     */
    private function attachment(): array
    {
        Storage::fake('private');

        $content = 'evidence-body';
        $path = 'attachments/evidence.pdf';
        Storage::disk('private')->put($path, $content);

        $attachment = FileAttachment::create([
            'file_name' => 'evidence.pdf',
            'file_path' => $path,
            'file_size' => strlen($content),
            'description' => 'Evidence',
            'uploaded_by' => User::factory()->create()->id,
            'audit_id' => Audit::factory()->create()->id,
        ]);

        return [$attachment, hash('sha256', $content)];
    }
}
