<?php

namespace Database\Factories;

use App\Enums\AccessAction;
use App\Models\AccessPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessPolicy>
 */
class AccessPolicyFactory extends Factory
{
    protected $model = AccessPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'AP-001',
            'name' => 'Standard access to restricted data',
            'description' => null,
            'tiers' => ['restricted'],
            'profiles' => [],
            'conditions' => ['min_trust' => 'high'],
            'action' => AccessAction::Allow,
            'mask_columns' => null,
            'max_duration_minutes' => null,
            'requires_justification' => false,
            'requires_review' => false,
            'priority' => 100,
            'version' => 1,
            'is_active' => true,
            'dry_run' => false,
            'owner_id' => User::factory(),
            'effective_from' => null,
            'review_due_at' => null,
            'notes' => null,
        ];
    }

    public function deny(): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => AccessAction::Deny,
        ]);
    }

    public function timeBoxed(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => AccessAction::TimeBoxed,
            'max_duration_minutes' => $minutes,
            'requires_justification' => true,
            'requires_review' => true,
        ]);
    }

    public function dryRun(): static
    {
        return $this->state(fn (array $attributes): array => [
            'dry_run' => true,
        ]);
    }
}
