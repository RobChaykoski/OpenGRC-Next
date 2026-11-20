<?php

namespace Database\Factories;

use App\Enums\RemediationTaskStatus;
use App\Models\RemediationTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemediationTask>
 */
class RemediationTaskFactory extends Factory
{
    protected $model = RemediationTask::class;

    public function definition(): array
    {
        return [
            'risk_id' => null,
            'finding_id' => null,
            'control_id' => null,
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(),
            'owner_id' => User::factory(),
            'due_at' => $this->faker->dateTimeBetween('-1 month', '+3 months'),
            'status' => $this->faker->randomElement(RemediationTaskStatus::cases()),
            'notes' => $this->faker->sentence(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => RemediationTaskStatus::InProgress,
            'due_at' => today()->subDays(5),
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => RemediationTaskStatus::Done,
            'completed_at' => now(),
        ]);
    }
}
