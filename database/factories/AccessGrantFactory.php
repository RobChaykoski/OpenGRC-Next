<?php

namespace Database\Factories;

use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use App\Models\AccessGrant;
use App\Models\ProtectedResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessGrant>
 */
class AccessGrantFactory extends Factory
{
    protected $model = AccessGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => 'user',
            'subject_id' => User::factory(),
            'subject_label' => 'ER physician',
            'protected_resource_id' => ProtectedResource::factory(),
            'purpose_code' => 'care',
            'justification' => 'Emergency treatment for an unassigned patient.',
            'is_break_glass' => false,
            'clinical_reason' => null,
            'state' => AccessGrantState::Requested,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(60),
            'review_required' => false,
            'review_outcome' => PrivacyReviewOutcome::NotApplicable,
            'requested_by' => User::factory(),
        ];
    }

    public function breakGlass(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_break_glass' => true,
            'clinical_reason' => 'trauma',
            'review_required' => true,
            'review_due_at' => today()->addDays(5),
            'review_outcome' => PrivacyReviewOutcome::Pending,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => AccessGrantState::Active,
            'approved_at' => now(),
            'starts_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => AccessGrantState::Expired,
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
