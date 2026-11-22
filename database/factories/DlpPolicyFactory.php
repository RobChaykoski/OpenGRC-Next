<?php

namespace Database\Factories;

use App\Enums\DlpChannel;
use App\Enums\DlpPolicyAction;
use App\Enums\DocumentClassification;
use App\Models\DlpPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DlpPolicy>
 */
class DlpPolicyFactory extends Factory
{
    protected $model = DlpPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DP-001',
            'name' => 'Customer identifiers in email',
            'description' => null,
            'channels' => [DlpChannel::Email->value],
            'classifications' => [DocumentClassification::Confidential->value],
            'identifier_types' => [],
            'action' => DlpPolicyAction::Alert,
            'owner_id' => User::factory(),
            'review_due_at' => today()->addDays(90),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_due_at' => today()->subDays(10),
        ]);
    }
}
