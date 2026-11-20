<?php

namespace Database\Factories;

use App\Enums\CrosswalkConfidence;
use App\Enums\CrosswalkStatus;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlCrosswalk>
 */
class ControlCrosswalkFactory extends Factory
{
    protected $model = ControlCrosswalk::class;

    public function definition(): array
    {
        return [
            'source_control_id' => Control::factory(),
            'target_control_id' => Control::factory(),
            'rationale' => $this->faker->paragraph(),
            'confidence' => $this->faker->randomElement(CrosswalkConfidence::cases()),
            'status' => CrosswalkStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CrosswalkStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
