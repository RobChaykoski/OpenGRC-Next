<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imported questionnaires group questions under section headings. A section is
 * a label, not a separate table: reordering questions must not orphan a group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->string('section', 191)->nullable()->after('survey_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
