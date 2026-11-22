<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only mirror of activity events (ADR 0056).
 *
 * The activity log stays the operational detail; this ledger is the evidence
 * that the sequence happened and that nobody edited it afterwards. It stores the
 * description and a hash of the properties, never a second copy of the payload.
 *
 * activity_id is a plain column, not a foreign key: pruning old activity must not
 * touch the ledger, and the ledger must not block the prune.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_ledger_entries')) {
            Schema::create('audit_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('activity_id')->nullable()->unique();
                $table->string('log_name', 64)->nullable();
                $table->string('event', 32);
                $table->nullableMorphs('subject');
                $table->nullableMorphs('actor');
                $table->text('description')->nullable();
                $table->char('properties_sha256', 64);
                $table->char('previous_hash', 64)->nullable();
                $table->char('entry_hash', 64);
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('occurred_at');
            });
        }

        $this->makeAppendOnly();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_ledger_entries');
    }

    private function makeAppendOnly(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_ledger_entries_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_ledger_entries_no_delete');
            DB::unprepared(
                'CREATE TRIGGER audit_ledger_entries_no_update
                 BEFORE UPDATE ON audit_ledger_entries
                 BEGIN
                     SELECT RAISE(ABORT, \'audit ledger is append-only\');
                 END;'
            );
            DB::unprepared(
                'CREATE TRIGGER audit_ledger_entries_no_delete
                 BEFORE DELETE ON audit_ledger_entries
                 BEGIN
                     SELECT RAISE(ABORT, \'audit ledger is append-only\');
                 END;'
            );
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_ledger_entries_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_ledger_entries_no_delete');
            DB::unprepared(
                "CREATE TRIGGER audit_ledger_entries_no_update
                 BEFORE UPDATE ON audit_ledger_entries
                 FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit ledger is append-only'"
            );
            DB::unprepared(
                "CREATE TRIGGER audit_ledger_entries_no_delete
                 BEFORE DELETE ON audit_ledger_entries
                 FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit ledger is append-only'"
            );
        }
    }
};
