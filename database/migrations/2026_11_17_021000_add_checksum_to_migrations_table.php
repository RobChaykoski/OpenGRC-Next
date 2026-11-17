<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('migrations', 'checksum')) {
            Schema::table('migrations', function (Blueprint $table) {
                $table->char('checksum', 64)->nullable()->after('batch');
                $table->timestamp('applied_at')->nullable()->after('checksum');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('migrations', 'checksum')) {
            Schema::table('migrations', function (Blueprint $table) {
                $table->dropColumn(['checksum', 'applied_at']);
            });
        }
    }
};
