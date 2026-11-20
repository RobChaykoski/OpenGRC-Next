<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('software_components') && ! Schema::hasColumn('software_components', 'scorecard_score')) {
            Schema::table('software_components', function (Blueprint $table) {
                $table->float('scorecard_score')->nullable();
                $table->date('scorecard_date')->nullable();
                $table->string('deps_dev_system', 16)->nullable();
                $table->timestamp('deps_dev_checked_at')->nullable();

                $table->index('scorecard_score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('software_components') && Schema::hasColumn('software_components', 'scorecard_score')) {
            Schema::table('software_components', function (Blueprint $table) {
                $table->dropIndex(['scorecard_score']);
                $table->dropColumn([
                    'scorecard_score',
                    'scorecard_date',
                    'deps_dev_system',
                    'deps_dev_checked_at',
                ]);
            });
        }
    }
};
