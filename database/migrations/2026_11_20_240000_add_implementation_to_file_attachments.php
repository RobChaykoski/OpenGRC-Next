<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow a file attachment to be human evidence for an implementation.
 *
 * FileAttachment already carries the append-only evidence ledger and the
 * SHA-256 chain. This adds the implementation link and makes the audit link
 * optional, because implementation evidence does not belong to an audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('file_attachments')) {
            return;
        }

        if (! Schema::hasColumn('file_attachments', 'implementation_id')) {
            Schema::table('file_attachments', function (Blueprint $table) {
                $table->foreignId('implementation_id')->nullable()->after('audit_id')
                    ->constrained('implementations')->nullOnDelete();

                $table->index('implementation_id');
            });
        }

        Schema::table('file_attachments', function (Blueprint $table) {
            $table->foreignId('audit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('file_attachments') && Schema::hasColumn('file_attachments', 'implementation_id')) {
            DB::table('file_attachments')->whereNotNull('implementation_id')->delete();

            Schema::table('file_attachments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('implementation_id');
            });
        }

        if (Schema::hasTable('file_attachments')) {
            Schema::table('file_attachments', function (Blueprint $table) {
                $table->foreignId('audit_id')->nullable(false)->change();
            });
        }
    }
};
