<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A risk without an owner is a observation, not a managed risk. Ownership is a
 * user reference rather than free text so the register can be filtered by person
 * and survives a rename.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
