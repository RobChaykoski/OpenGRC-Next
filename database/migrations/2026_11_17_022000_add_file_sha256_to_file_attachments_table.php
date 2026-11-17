<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('file_attachments', 'file_sha256')) {
            Schema::table('file_attachments', function (Blueprint $table) {
                $table->char('file_sha256', 64)->nullable()->after('file_size');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('file_attachments', 'file_sha256')) {
            Schema::table('file_attachments', function (Blueprint $table) {
                $table->dropColumn('file_sha256');
            });
        }
    }
};
