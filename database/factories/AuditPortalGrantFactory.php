<?php

namespace Database\Factories;

use App\Models\Audit;
use App\Models\AuditPortalGrant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditPortalGrant>
 */
class AuditPortalGrantFactory extends Factory
{
    protected $model = AuditPortalGrant::class;

    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'label' => $this->faker->company().' auditor',
            'token_hash' => AuditPortalGrant::hashToken(Str::random(AuditPortalGrant::TOKEN_LENGTH)),
            'expires_at' => now()->addDays(30),
            'access_count' => 0,
        ];
    }

    public function forToken(string $token): static
    {
        return $this->state(fn (): array => [
            'token_hash' => AuditPortalGrant::hashToken($token),
        ]);
    }

    public function forAudit(Audit $audit): static
    {
        return $this->state(fn (): array => [
            'audit_id' => $audit->getKey(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }
}
