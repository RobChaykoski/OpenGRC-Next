<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a decision to the grant it created (ADR 0052).
 *
 * A break-glass decision used to answer `time_boxed` and stop there, leaving the
 * privacy review obligation unrecorded. The endpoint now mints the grant in the
 * same transaction as the decision, and this column is how an auditor walks from
 * the decision to the entitlement it produced.
 *
 * Decisions are append-only: the link is set once, at insert, and never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('access_decisions') || ! Schema::hasTable('access_grants')) {
            return;
        }

        if (Schema::hasColumn('access_decisions', 'access_grant_id')) {
            return;
        }

        Schema::table('access_decisions', function (Blueprint $table) {
            $table->foreignId('access_grant_id')
                ->nullable()
                ->after('matched_policy_id')
                ->constrained('access_grants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_decisions') || ! Schema::hasColumn('access_decisions', 'access_grant_id')) {
            return;
        }

        Schema::table('access_decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_grant_id');
        });
    }
};
