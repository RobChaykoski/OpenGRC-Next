<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a DLP finding to the policy that owns it, the risk it evidences and the
 * control it tests (ADR 0053).
 *
 * The handoff recorded the manual risk handoff as a gap. These are nullable
 * references on purpose: a finding with no policy is a coverage gap, not an
 * error, and the UI surfaces it as one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlp_findings')) {
            return;
        }

        if (! Schema::hasColumn('dlp_findings', 'dlp_policy_id')) {
            Schema::table('dlp_findings', function (Blueprint $table) {
                $table->foreignId('dlp_policy_id')
                    ->nullable()
                    ->after('source')
                    ->constrained('dlp_policies')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('dlp_findings', 'risk_id')) {
            Schema::table('dlp_findings', function (Blueprint $table) {
                $table->foreignId('risk_id')
                    ->nullable()
                    ->after('dlp_policy_id')
                    ->constrained('risks')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('dlp_findings', 'control_id')) {
            Schema::table('dlp_findings', function (Blueprint $table) {
                $table->foreignId('control_id')
                    ->nullable()
                    ->after('risk_id')
                    ->constrained('controls')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlp_findings')) {
            return;
        }

        if (Schema::hasColumn('dlp_findings', 'control_id')) {
            Schema::table('dlp_findings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('control_id');
            });
        }

        if (Schema::hasColumn('dlp_findings', 'risk_id')) {
            Schema::table('dlp_findings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('risk_id');
            });
        }

        if (Schema::hasColumn('dlp_findings', 'dlp_policy_id')) {
            Schema::table('dlp_findings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('dlp_policy_id');
            });
        }
    }
};
