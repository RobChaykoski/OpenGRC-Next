<?php

namespace Database\Factories;

use App\Enums\PrivacyRegime;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyRequest>
 */
class PrivacyRequestFactory extends Factory
{
    protected $model = PrivacyRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'regime' => PrivacyRegime::Gdpr,
            'request_type' => PrivacyRequestType::Access,
            'status' => PrivacyRequestStatus::InProgress,
            'requester_name' => $this->faker->name(),
            'contact_reference' => 'case-'.$this->faker->uuid(),
            'owner_id' => User::factory(),
            'received_at' => today()->subDays(5),
            'due_at' => null,
            'extended_at' => null,
            'extension_reason' => null,
            'identity_verified' => true,
            'verification_method' => 'Verified against the existing customer account.',
            'verified_at' => today()->subDays(4),
            'denial_reason' => null,
            'response_summary' => null,
            'completed_at' => null,
            'evidence_reference' => 'vault://privacy/'.$this->faker->uuid(),
            'evidence_sha256' => hash('sha256', $this->faker->uuid()),
            'notes' => null,
        ];
    }

    public function gdpr(): static
    {
        return $this->state(fn (array $attributes): array => [
            'regime' => PrivacyRegime::Gdpr,
        ]);
    }

    public function ccpa(): static
    {
        return $this->state(fn (array $attributes): array => [
            'regime' => PrivacyRegime::Ccpa,
            'request_type' => PrivacyRequestType::OptOutSaleShare,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PrivacyRequestStatus::InProgress,
            'received_at' => today()->subDays(60),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PrivacyRequestStatus::Completed,
            'completed_at' => today(),
            'response_summary' => 'Response package delivered to the requester.',
        ]);
    }
}
