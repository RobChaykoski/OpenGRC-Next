<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DLP review disposition and normalised identifier codes.
 *
 * The finding still contains no content. Disposition is a human review outcome;
 * identifier_types lets false-positive rates be measured by detector rather than
 * by the human-readable label alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlp_findings')) {
            return;
        }

        Schema::table('dlp_findings', function (Blueprint $table) {
            if (! Schema::hasColumn('dlp_findings', 'identifier_types')) {
                $table->json('identifier_types')->nullable()->after('match_types');
            }

            if (! Schema::hasColumn('dlp_findings', 'disposition')) {
                $table->string('disposition', 16)->nullable()->after('action');
            }

            if (! Schema::hasColumn('dlp_findings', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('disposition')
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('dlp_findings', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (! Schema::hasColumn('dlp_findings', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('dlp_findings', function (Blueprint $table) {
            $table->index(['disposition', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlp_findings')) {
            return;
        }

        Schema::table('dlp_findings', function (Blueprint $table) {
            $table->dropIndex(['disposition', 'reviewed_at']);

            if (Schema::hasColumn('dlp_findings', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            foreach (['review_notes', 'reviewed_at', 'disposition', 'identifier_types'] as $column) {
                if (Schema::hasColumn('dlp_findings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
