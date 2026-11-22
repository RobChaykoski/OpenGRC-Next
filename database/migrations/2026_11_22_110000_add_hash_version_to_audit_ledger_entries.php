<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version the audit ledger hash (ADR 0056).
 *
 * Activity changes are stored in `attribute_changes`, not `properties`, so the
 * first entries hashed an empty payload. Since the ledger is append-only, the
 * algorithm is versioned rather than rewritten: version 1 entries verify with the
 * old payload, and new entries record `hash_version = 2` and hash the attribute
 * changes as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_ledger_entries') || Schema::hasColumn('audit_ledger_entries', 'hash_version')) {
            return;
        }

        Schema::table('audit_ledger_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('hash_version')->default(1)->after('entry_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_ledger_entries') || ! Schema::hasColumn('audit_ledger_entries', 'hash_version')) {
            return;
        }

        Schema::table('audit_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('hash_version');
        });
    }
};
