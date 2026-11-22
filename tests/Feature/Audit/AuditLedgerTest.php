<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLedgerEntry;
use App\Models\User;
use App\Services\Audit\AuditLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLedgerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_activity_is_mirrored_into_the_ledger(): void
    {
        $user = User::factory()->create();

        activity()->causedBy($user)->event('updated')->log('Changed the access policy');

        $entry = AuditLedgerEntry::query()->where('description', 'Changed the access policy')->sole();

        $this->assertSame('updated', $entry->event);
        $this->assertSame($user->getKey(), $entry->actor_id);
        $this->assertSame(User::class, $entry->actor_type);
        $this->assertNotNull($entry->occurred_at);
        $this->assertNotNull($entry->entry_hash);
    }

    #[Test]
    public function entries_form_a_hash_chain(): void
    {
        activity()->event('created')->log('First event');
        activity()->event('updated')->log('Second event');

        [$first, $second] = AuditLedgerEntry::query()->orderBy('id')->get()->all();

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->entry_hash, $second->previous_hash);
        $this->assertSame($second->entry_hash, $second->calculateEntryHash($first->entry_hash));
    }

    #[Test]
    public function the_properties_are_hashed_not_copied(): void
    {
        activity()
            ->withProperties(['note' => 'super-secret-value'])
            ->event('updated')
            ->log('Property test');

        $entry = AuditLedgerEntry::query()->where('description', 'Property test')->sole();
        $stored = json_encode($entry->getAttributes(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('super-secret-value', $stored);
        $this->assertSame(64, mb_strlen($entry->properties_sha256));
    }

    #[Test]
    public function editing_an_activity_row_is_detected(): void
    {
        activity()->event('updated')->log('Original description');

        $activity = DB::table('activity_log')->where('description', 'Original description')->firstOrFail();

        DB::table('activity_log')->where('id', $activity->id)->update(['description' => 'Edited description']);

        $report = app(AuditLedger::class)->verify();

        $this->assertNull($report['broken']);
        $this->assertSame(1, $report['altered']);
    }

    #[Test]
    public function a_pruned_activity_row_is_reported_but_not_a_failure(): void
    {
        activity()->event('updated')->log('Will be pruned');

        $activity = DB::table('activity_log')->where('description', 'Will be pruned')->firstOrFail();
        DB::table('activity_log')->where('id', $activity->id)->delete();

        $report = app(AuditLedger::class)->verify();

        $this->assertNull($report['broken']);
        $this->assertSame(0, $report['altered']);
        $this->assertSame(1, $report['missing']);
    }

    #[Test]
    public function the_ledger_is_append_only_at_the_model_layer(): void
    {
        $entry = AuditLedgerEntry::query()->first() ?? $this->appendOne();

        $this->expectException(LogicException::class);

        $entry->update(['description' => 'edited']);
    }

    #[Test]
    public function the_ledger_cannot_be_deleted(): void
    {
        $entry = AuditLedgerEntry::query()->first() ?? $this->appendOne();

        $this->expectException(LogicException::class);

        $entry->delete();
    }

    #[Test]
    public function the_database_trigger_blocks_a_direct_update(): void
    {
        $entry = AuditLedgerEntry::query()->first() ?? $this->appendOne();

        $this->expectException(QueryException::class);

        DB::table('audit_ledger_entries')->where('id', $entry->getKey())->update(['description' => 'edited']);
    }

    #[Test]
    public function verification_finds_a_broken_chain(): void
    {
        activity()->event('created')->log('First');
        activity()->event('updated')->log('Second');

        $second = AuditLedgerEntry::query()->orderByDesc('id')->firstOrFail();

        // Drop the guard the migration installed; a direct writer would not run it.
        DB::unprepared('DROP TRIGGER IF EXISTS audit_ledger_entries_no_update');
        DB::table('audit_ledger_entries')->where('id', $second->getKey())->update([
            'entry_hash' => str_repeat('a', 64),
        ]);

        $report = app(AuditLedger::class)->verify();

        $this->assertSame((int) $second->getKey(), $report['broken']);
    }

    #[Test]
    public function the_verify_command_passes_on_an_intact_ledger(): void
    {
        activity()->event('created')->log('Intact');

        $this->artisan('opengrc:audit:verify')
            ->expectsOutputToContain('Audit ledger intact')
            ->assertSuccessful();
    }

    #[Test]
    public function the_verify_command_fails_on_an_altered_activity(): void
    {
        activity()->event('updated')->log('Will be altered');

        $activity = DB::table('activity_log')->where('description', 'Will be altered')->firstOrFail();
        DB::table('activity_log')->where('id', $activity->id)->update(['description' => 'Altered']);

        $this->artisan('opengrc:audit:verify')
            ->expectsOutputToContain('no longer match')
            ->assertFailed();
    }

    protected function appendOne(): AuditLedgerEntry
    {
        activity()->event('created')->log('Bootstrap entry');

        return AuditLedgerEntry::query()->orderByDesc('id')->firstOrFail();
    }
}
