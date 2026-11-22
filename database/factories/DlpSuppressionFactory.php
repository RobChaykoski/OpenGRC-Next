<?php

namespace Database\Factories;

use App\Enums\DlpSuppressionState;
use App\Models\DlpPolicy;
use App\Models\DlpSuppression;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DlpSuppression>
 */
class DlpSuppressionFactory extends Factory
{
    protected $model = DlpSuppression::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dlp_policy_id' => DlpPolicy::factory(),
            'identifier_type' => null,
            'match_type' => null,
            'justification' => 'The detector matches a public test series; no customer data is involved.',
            'requested_by' => User::factory(),
            'approved_by' => null,
            'state' => DlpSuppressionState::Requested,
            'starts_at' => null,
            'expires_at' => now()->addDays(30),
            'approved_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => DlpSuppressionState::Active,
            'starts_at' => now(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => DlpSuppressionState::Active,
            'starts_at' => now()->subDays(40),
            'expires_at' => now()->subDays(1),
            'approved_at' => now()->subDays(40),
            'approved_by' => User::factory(),
        ]);
    }
}
