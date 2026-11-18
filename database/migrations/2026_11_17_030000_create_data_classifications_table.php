<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_classifications')) {
            Schema::create('data_classifications', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('label');
                $table->string('level', 32)->default('internal');
                $table->text('description')->nullable();
                $table->json('industries')->nullable();
                $table->boolean('is_system')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        $now = now();

        foreach (config('data_classification.common', []) as $label) {
            $this->seedLabel($label, null, $now);
        }

        foreach (config('data_classification.industries', []) as $industry => $definition) {
            foreach ($definition['labels'] ?? [] as $label) {
                $this->seedLabel($label, [$industry], $now);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_classifications');
    }

    /**
     * @param  array<string, mixed>  $label
     * @param  array<int, string>|null  $industries
     */
    private function seedLabel(array $label, ?array $industries, mixed $now): void
    {
        $existing = DB::table('data_classifications')->where('code', $label['code'])->first();

        if ($existing) {
            $merged = $existing->industries ? array_values(array_unique(array_merge(
                json_decode($existing->industries, true) ?: [],
                $industries ?? []
            ))) : $industries;

            DB::table('data_classifications')->where('id', $existing->id)->update([
                'label' => $label['label'],
                'level' => $label['level'],
                'description' => $label['description'] ?? null,
                'industries' => $merged ? json_encode($merged) : null,
                'is_system' => true,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('data_classifications')->insert([
            'code' => $label['code'],
            'label' => $label['label'],
            'level' => $label['level'],
            'description' => $label['description'] ?? null,
            'industries' => $industries ? json_encode($industries) : null,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
