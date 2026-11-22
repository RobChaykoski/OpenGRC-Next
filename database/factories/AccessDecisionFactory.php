<?php

namespace Database\Factories;

use App\Enums\AccessAction;
use App\Enums\AccessorProfile;
use App\Enums\AccessReason;
use App\Enums\DocumentClassification;
use App\Models\AccessDecision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessDecision>
 */
class AccessDecisionFactory extends Factory
{
    protected $model = AccessDecision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'correlation_id' => (string) Str::uuid(),
            'subject_type' => 'user',
            // A label is a display name or service id, not an email address
            // (ADR 0049). Keep the factory honest so tests model the rule.
            'subject_label' => 'analyst',
            'resource_ref' => 'clinical.records',
            'tier' => DocumentClassification::Restricted,
            'profile' => AccessorProfile::Standard,
            'context' => ['trust' => 'high'],
            'action' => AccessAction::Allow,
            'reason' => AccessReason::PolicyMatch,
            'risk_weight' => 8,
            'decided_at' => now(),
        ];
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => AccessAction::Deny,
            'reason' => AccessReason::NoPolicy,
        ]);
    }
}
