<?php

namespace Database\Factories;

use App\Models\DlpFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DlpFinding>
 */
class DlpFindingFactory extends Factory
{
    protected $model = DlpFinding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'upload',
            'severity' => 'medium',
            'match_types' => ['Email address'],
            'identifier_types' => ['email'],
            'action' => 'flagged',
            'content_sha256' => hash('sha256', 'sample-content'),
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => 'blocked',
            'severity' => 'high',
        ]);
    }
}
