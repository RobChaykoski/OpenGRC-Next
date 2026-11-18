<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Continuous control monitoring (ADR 0009).
 *
 * Sources describe where a measurement came from, jobs are operational
 * history, and records are the evidence itself: normalised, hashed, and
 * append-only. Validation rules and their results are kept per rule version so
 * a failed check can be explained months later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evidence_sources')) {
            Schema::create('evidence_sources', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('type', 32);
                $table->boolean('enabled')->default(true);
                // Encrypted at the model layer. Never store tokens in plain text.
                $table->text('configuration')->nullable();
                $table->string('schedule', 64)->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['type', 'enabled']);
            });
        }

        if (! Schema::hasTable('evidence_jobs')) {
            Schema::create('evidence_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->constrained('evidence_sources')->cascadeOnDelete();
                $table->string('status', 32)->default('queued');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('records_seen')->default(0);
                $table->unsignedInteger('records_created')->default(0);
                $table->unsignedInteger('records_duplicate')->default(0);
                $table->unsignedInteger('records_failed')->default(0);
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['source_id', 'started_at']);
            });
        }

        if (! Schema::hasTable('evidence_records')) {
            Schema::create('evidence_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')->constrained('evidence_sources')->restrictOnDelete();
                $table->foreignId('job_id')->nullable()->constrained('evidence_jobs')->nullOnDelete();
                $table->string('external_id', 191);
                $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
                $table->foreignId('control_id')->nullable()->constrained('controls')->nullOnDelete();
                $table->string('evidence_type', 64);
                $table->string('status', 32)->default('unknown');
                $table->timestamp('collected_at');
                $table->json('payload');
                $table->char('payload_sha256', 64);
                $table->string('idempotency_key', 64)->unique();
                $table->foreignId('supersedes_id')->nullable()->constrained('evidence_records')->restrictOnDelete();
                $table->timestamp('ingested_at')->nullable();
                $table->timestamp('created_at')->nullable();

                // A replay of the same measurement must never create a second row.
                $table->unique(['source_id', 'external_id', 'collected_at'], 'evidence_records_measurement_unique');
                $table->index(['source_id', 'collected_at']);
                $table->index(['control_id', 'collected_at']);
                $table->index(['asset_id', 'collected_at']);
            });
        }

        if (! Schema::hasTable('control_validation_rules')) {
            Schema::create('control_validation_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
                $table->foreignId('source_id')->nullable()->constrained('evidence_sources')->nullOnDelete();
                $table->json('definition');
                $table->string('severity', 16)->default('medium');
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->unique(['control_id', 'source_id', 'version'], 'control_validation_rules_version_unique');
            });
        }

        if (! Schema::hasTable('control_validations')) {
            Schema::create('control_validations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
                $table->foreignId('source_id')->nullable()->constrained('evidence_sources')->nullOnDelete();
                $table->foreignId('evidence_record_id')->nullable()->constrained('evidence_records')->nullOnDelete();
                $table->foreignId('rule_id')->nullable()->constrained('control_validation_rules')->nullOnDelete();
                $table->unsignedInteger('rule_version')->nullable();
                $table->string('status', 32)->default('unknown');
                $table->json('result')->nullable();
                $table->timestamp('evaluated_at');
                $table->timestamps();

                $table->index(['control_id', 'evaluated_at']);
            });
        }

        $this->makeEvidenceRecordsAppendOnly();
    }

    public function down(): void
    {
        Schema::dropIfExists('control_validations');
        Schema::dropIfExists('control_validation_rules');
        Schema::dropIfExists('evidence_records');
        Schema::dropIfExists('evidence_jobs');
        Schema::dropIfExists('evidence_sources');
    }

    /**
     * Evidence records are the audit trail. Block UPDATE and DELETE where the
     * deployment target supports triggers; the model guard and the verifier
     * cover the rest.
     */
    private function makeEvidenceRecordsAppendOnly(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS evidence_records_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS evidence_records_no_delete');
            DB::unprepared(
                'CREATE TRIGGER evidence_records_no_update
                 BEFORE UPDATE ON evidence_records
                 BEGIN
                     SELECT RAISE(ABORT, \'evidence records are append-only\');
                 END;'
            );
            DB::unprepared(
                'CREATE TRIGGER evidence_records_no_delete
                 BEFORE DELETE ON evidence_records
                 BEGIN
                     SELECT RAISE(ABORT, \'evidence records are append-only\');
                 END;'
            );
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::unprepared('DROP TRIGGER IF EXISTS evidence_records_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS evidence_records_no_delete');
            DB::unprepared(
                "CREATE TRIGGER evidence_records_no_update
                 BEFORE UPDATE ON evidence_records
                 FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evidence records are append-only'"
            );
            DB::unprepared(
                "CREATE TRIGGER evidence_records_no_delete
                 BEFORE DELETE ON evidence_records
                 FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evidence records are append-only'"
            );
        }
    }
};
