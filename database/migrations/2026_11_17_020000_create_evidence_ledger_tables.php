<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('sequence');
            $table->string('event', 32);
            $table->nullableMorphs('actor');
            $table->text('reason')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->string('file_path')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('entry_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['subject_type', 'subject_id', 'sequence'], 'evidence_ledger_subject_sequence_unique');
            $table->index(['subject_type', 'subject_id'], 'evidence_ledger_subject_index');
        });

        Schema::create('evidence_states', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 32)->default('active');
            $table->text('reason')->nullable();
            $table->nullableMorphs('actor');
            $table->timestamp('changed_at')->nullable();
            $table->nullableMorphs('superseded_by');
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id'], 'evidence_states_subject_unique');
        });

        $this->makeLedgerAppendOnly();
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_states');
        Schema::dropIfExists('evidence_ledger_entries');
    }

    /**
     * A model guard is not enough for an audit ledger: block UPDATE and DELETE
     * at the database layer as well. SQLite and MySQL/MariaDB are the supported
     * deployment targets; other drivers rely on the model guard and the verifier.
     */
    private function makeLedgerAppendOnly(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared(
                'CREATE TRIGGER evidence_ledger_entries_no_update
                 BEFORE UPDATE ON evidence_ledger_entries
                 BEGIN
                     SELECT RAISE(ABORT, \'evidence ledger is append-only\');
                 END;'
            );
            DB::unprepared(
                'CREATE TRIGGER evidence_ledger_entries_no_delete
                 BEFORE DELETE ON evidence_ledger_entries
                 BEGIN
                     SELECT RAISE(ABORT, \'evidence ledger is append-only\');
                 END;'
            );
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::unprepared(
                "CREATE TRIGGER evidence_ledger_entries_no_update
                 BEFORE UPDATE ON evidence_ledger_entries
                 FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evidence ledger is append-only'"
            );
            DB::unprepared(
                "CREATE TRIGGER evidence_ledger_entries_no_delete
                 BEFORE DELETE ON evidence_ledger_entries
                 FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evidence ledger is append-only'"
            );
        }
    }
};
