<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per framework per day: the score at that point, with its components.
 *
 * Storing the components keeps an old trend point explainable even after the
 * weighting or the data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assurance_snapshots')) {
            Schema::create('assurance_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('framework_code', 64);
                $table->date('snapshot_date');
                $table->unsignedTinyInteger('score');
                $table->json('components');
                $table->json('counts');
                $table->timestamps();

                $table->unique(['framework_code', 'snapshot_date'], 'assurance_snapshots_framework_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assurance_snapshots');
    }
};
