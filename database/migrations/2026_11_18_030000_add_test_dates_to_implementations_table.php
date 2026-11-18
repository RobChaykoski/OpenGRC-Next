<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control testing has a cadence. Recording when an implementation was last
 * tested and when the next test is due turns "effectiveness: unknown" into a
 * date a programme manager can act on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementations', function (Blueprint $table) {
            $table->date('last_tested_at')->nullable()->after('effectiveness');
            $table->date('next_test_at')->nullable()->after('last_tested_at');
        });
    }

    public function down(): void
    {
        Schema::table('implementations', function (Blueprint $table) {
            $table->dropColumn(['last_tested_at', 'next_test_at']);
        });
    }
};
