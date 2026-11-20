<?php

namespace Database\Factories;

use App\Enums\RegulatoryChangeImpact;
use App\Enums\RegulatoryChangeStatus;
use App\Models\RegulatoryChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegulatoryChange>
 */
class RegulatoryChangeFactory extends Factory
{
    protected $model = RegulatoryChange::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'regulator' => $this->faker->randomElement(['NIST', 'PCI SSC', 'ISO/IEC', 'European Commission', 'OSFI']),
            'reference_url' => $this->faker->url(),
            'published_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
            'effective_at' => $this->faker->dateTimeBetween('now', '+12 months'),
            'status' => $this->faker->randomElement(RegulatoryChangeStatus::cases()),
            'impact' => $this->faker->randomElement(RegulatoryChangeImpact::cases()),
            'summary' => $this->faker->paragraph(),
            'impact_assessment' => $this->faker->paragraph(),
            'assessed_at' => $this->faker->optional()->dateTimeBetween('-2 months', 'now'),
            'due_at' => $this->faker->optional()->dateTimeBetween('-1 month', '+3 months'),
            'notes' => $this->faker->sentence(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => RegulatoryChangeStatus::Assessing,
            'due_at' => today()->addDays(14),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => RegulatoryChangeStatus::Assessing,
            'due_at' => today()->subDays(5),
        ]);
    }
}
