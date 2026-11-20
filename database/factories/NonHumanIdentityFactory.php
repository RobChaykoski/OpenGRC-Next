<?php

namespace Database\Factories;

use App\Enums\NonHumanIdentityStatus;
use App\Enums\NonHumanIdentityType;
use App\Models\NonHumanIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonHumanIdentity>
 */
class NonHumanIdentityFactory extends Factory
{
    protected $model = NonHumanIdentity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->bothify('Service credential ###'),
            'type' => NonHumanIdentityType::ApiToken,
            'system' => 'Wazuh',
            'environment' => 'production',
            'owner_id' => User::factory(),
            'purpose' => 'Read SCA results for evidence collection.',
            'scope' => 'agent:read, sca:read on agent:id:*',
            'secret_reference' => 'vault://kv/opengrc/wazuh-api',
            'rotation_days' => 90,
            'last_rotated_at' => today()->subDays(30),
            'next_rotation_at' => null,
            'last_used_at' => today()->subDay(),
            'expires_at' => null,
            'status' => NonHumanIdentityStatus::Active,
            'reviewed_at' => today()->subDays(20),
            'next_review_at' => today()->addDays(70),
            'revoked_at' => null,
            'revocation_reason' => null,
            'notes' => null,
        ];
    }

    public function rotationDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_rotated_at' => today()->subDays(120),
            'status' => NonHumanIdentityStatus::RotationDue,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => today()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => NonHumanIdentityStatus::Revoked,
            'revoked_at' => today()->subDay(),
            'revocation_reason' => 'Integration retired.',
        ]);
    }

    public function certificate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NonHumanIdentityType::Certificate,
            'name' => 'Discovery console TLS certificate',
            'system' => 'OpenDiscoveryEngine',
            'secret_reference' => 'vault://pki/opengrc/discovery-console',
        ]);
    }
}
