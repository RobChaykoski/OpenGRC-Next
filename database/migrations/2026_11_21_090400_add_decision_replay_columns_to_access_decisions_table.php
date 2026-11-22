<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay protection for the access decision endpoint (ADR 0051).
 *
 * An enforcement point retries a decision request when it does not receive an
 * answer. The key is scoped to the policy enforcement point so a retry returns
 * the recorded decision instead of appending a second one and consuming the
 * exposure window twice.
 *
 * The request hash is what makes a reused key visible: the same key with a
 * different body is a client defect or an attempt to re-label a decision, not a
 * retry. It is recorded, not trusted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('access_decisions')) {
            return;
        }

        if (! Schema::hasColumn('access_decisions', 'idempotency_key')) {
            Schema::table('access_decisions', function (Blueprint $table) {
                $table->string('idempotency_key', 128)->nullable()->after('pep');
            });
        }

        if (! Schema::hasColumn('access_decisions', 'request_hash')) {
            Schema::table('access_decisions', function (Blueprint $table) {
                $table->string('request_hash', 64)->nullable()->after('idempotency_key');
            });
        }

        if (! Schema::hasIndex('access_decisions', 'access_decisions_pep_idempotency_unique')) {
            Schema::table('access_decisions', function (Blueprint $table) {
                // Null keys are not deduplicated: only a caller that asks for a
                // retry-safe request gets one.
                $table->unique(['pep', 'idempotency_key'], 'access_decisions_pep_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_decisions')) {
            return;
        }

        if (Schema::hasIndex('access_decisions', 'access_decisions_pep_idempotency_unique')) {
            Schema::table('access_decisions', function (Blueprint $table) {
                $table->dropUnique('access_decisions_pep_idempotency_unique');
            });
        }

        foreach (['request_hash', 'idempotency_key'] as $column) {
            if (Schema::hasColumn('access_decisions', $column)) {
                Schema::table('access_decisions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
