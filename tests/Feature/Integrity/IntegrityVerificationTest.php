<?php

namespace Tests\Feature\Integrity;

use App\Enums\IntegrityCheckStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Models\Audit;
use App\Models\AuditLedgerEntry;
use App\Models\FileAttachment;
use App\Models\IntegrityCheck;
use App\Models\User;
use App\Services\Evidence\EvidenceLedger;
use App\Services\Integrity\IntegrityVerifier;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Scheduled verification of the two append-only chains.
 *
 * The chains already have detail commands and doctor checks. These tests cover
 * the scheduled path: a row per chain, a non-zero exit on failure, the stale
 * signal, and the retention window.
 */
class IntegrityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function the_command_records_a_passing_run_for_both_chains(): void
    {
        activity()->event('created')->log('Baseline event');

        $this->artisan('opengrc:integrity:verify')
            ->expectsOutputToContain('Evidence ledger: PASS')
            ->expectsOutputToContain('Audit ledger: PASS')
            ->assertSuccessful();

        $this->assertSame(2, IntegrityCheck::query()->count());
        $this->assertSame(IntegrityCheckStatus::Pass, IntegrityCheck::latestFor('evidence')->status);
        $this->assertSame(IntegrityCheckStatus::Pass, IntegrityCheck::latestFor('audit')->status);
    }

    #[Test]
    public function a_replaced_evidence_file_fails_the_run_and_is_recorded(): void
    {
        [$attachment] = $this->attachment();

        Storage::disk('private')->put($attachment->file_path, 'tampered');

        $this->artisan('opengrc:integrity:verify', ['--check' => 'evidence'])
            ->expectsOutputToContain('FAIL')
            ->assertFailed();

        $run = IntegrityCheck::latestFor('evidence');

        $this->assertNotNull($run);
        $this->assertSame(IntegrityCheckStatus::Fail, $run->status);
        $this->assertSame(1, $run->detail['error_count']);
        $this->assertStringContainsString('hash does not match', $run->detail['errors'][0]);

        // The failing chain is the evidence one; the audit chain was not run.
        $this->assertNull(IntegrityCheck::latestFor('audit'));
    }

    #[Test]
    public function a_broken_audit_chain_fails_the_audit_check(): void
    {
        activity()->event('created')->log('First');
        activity()->event('updated')->log('Second');

        $second = AuditLedgerEntry::query()->orderByDesc('id')->firstOrFail();

        // Drop the guard the migration installed; a direct writer would not run it.
        DB::unprepared('DROP TRIGGER IF EXISTS audit_ledger_entries_no_update');
        DB::table('audit_ledger_entries')->where('id', $second->getKey())->update([
            'entry_hash' => str_repeat('a', 64),
        ]);

        $this->artisan('opengrc:integrity:verify', ['--check' => 'audit'])
            ->expectsOutputToContain('FAIL')
            ->assertFailed();

        $run = IntegrityCheck::latestFor('audit');

        $this->assertNotNull($run);
        $this->assertSame(IntegrityCheckStatus::Fail, $run->status);
        $this->assertStringContainsString('broken', $run->summary);
    }

    #[Test]
    public function warnings_are_recorded_without_failing_unless_strict(): void
    {
        $this->mock(EvidenceLedger::class, function ($mock): void {
            $mock->shouldReceive('verifyAll')->andReturn([
                'checked' => 3,
                'errors' => [],
                'warnings' => ['Policy#1: ledger entry has no file hash'],
            ]);
        });

        $this->artisan('opengrc:integrity:verify', ['--check' => 'evidence'])
            ->assertSuccessful();

        $run = IntegrityCheck::latestFor('evidence');

        $this->assertNotNull($run);
        $this->assertSame(IntegrityCheckStatus::Warn, $run->status);
        $this->assertSame(1, $run->detail['warning_count']);
        $this->assertSame(0, $run->detail['error_count']);

        $this->artisan('opengrc:integrity:verify', ['--check' => 'evidence', '--strict' => true])
            ->assertFailed();
    }

    #[Test]
    public function an_unknown_check_is_rejected_without_writing_a_run(): void
    {
        $this->artisan('opengrc:integrity:verify', ['--check' => 'packets'])
            ->expectsOutputToContain('Unknown check')
            ->assertExitCode(2);

        $this->assertSame(0, IntegrityCheck::query()->count());
    }

    #[Test]
    public function the_dashboard_shows_a_verified_chain_as_intact(): void
    {
        activity()->event('created')->log('Baseline event');

        $this->artisan('opengrc:integrity:verify')->assertSuccessful();

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('integrity.widget.title'))
            ->assertSee(__('integrity.widget.value.pass'));
    }

    #[Test]
    public function the_dashboard_shows_a_stopped_schedule_as_attention(): void
    {
        foreach (IntegrityVerifier::CHECKS as $check) {
            IntegrityCheck::create([
                'check' => $check,
                'status' => IntegrityCheckStatus::Pass,
                'summary' => 'Checked; chain intact.',
                'detail' => [],
                'duration_ms' => 5,
                'checked_at' => now()->subDays(3),
            ]);
        }

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('integrity.widget.value.warn'))
            ->assertSee('schedule may have stopped');
    }

    #[Test]
    public function the_dashboard_distinguishes_a_missing_chain_from_a_pass(): void
    {
        IntegrityCheck::create([
            'check' => 'evidence',
            'status' => IntegrityCheckStatus::Pass,
            'summary' => 'Checked; chain intact.',
            'detail' => [],
            'duration_ms' => 5,
            'checked_at' => now(),
        ]);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('integrity.widget.value.never'))
            ->assertDontSee(__('integrity.widget.value.pass'));
    }

    #[Test]
    public function runs_older_than_the_retention_window_are_pruned(): void
    {
        config(['integrity.retention_days' => 30]);

        $old = IntegrityCheck::create([
            'check' => 'audit',
            'status' => IntegrityCheckStatus::Pass,
            'summary' => 'Old run.',
            'detail' => [],
            'duration_ms' => 5,
            'checked_at' => now()->subDays(60),
        ]);

        $this->artisan('opengrc:integrity:verify', ['--check' => 'audit'])
            ->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertSame(1, IntegrityCheck::query()->count());
    }

    /**
     * @return array{0: FileAttachment}
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

        return [$attachment];
    }
}
