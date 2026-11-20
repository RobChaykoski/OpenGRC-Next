<?php

namespace Database\Factories;

use App\Enums\AccessReviewStatus;
use App\Models\AccessReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessReview>
 */
class AccessReviewFactory extends Factory
{
    protected $model = AccessReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Quarterly access review',
            'status' => AccessReviewStatus::Draft,
            'reviewer_id' => User::factory(),
            'created_by' => User::factory(),
            'due_at' => today()->addWeeks(2),
            'started_at' => null,
            'completed_at' => null,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccessReviewStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccessReviewStatus::Completed,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
