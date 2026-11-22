<?php

namespace Database\Factories;

use App\Enums\AccessResourceType;
use App\Enums\ConsentDirective;
use App\Enums\DocumentClassification;
use App\Models\ProtectedResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtectedResource>
 */
class ProtectedResourceFactory extends Factory
{
    protected $model = ProtectedResource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Clinical records',
            'resource_type' => AccessResourceType::Dataset,
            'tier' => DocumentClassification::Restricted,
            'consent_directive' => ConsentDirective::None,
            'is_crown_jewel' => false,
            'classification_source' => 'manual',
            'classified_at' => now(),
            'notes' => null,
        ];
    }

    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'classified_at' => now()->subDays(90),
        ]);
    }

    public function lockbox(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consent_directive' => ConsentDirective::Lockbox,
        ]);
    }
}
