<?php

namespace Database\Factories;

use App\Enums\GuidanceStatus;
use App\Models\GuidanceNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuidanceNote>
 */
class GuidanceNoteFactory extends Factory
{
    protected $model = GuidanceNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Top 10 AI security actions: a primer',
            'publisher' => 'Canadian Centre for Cyber Security',
            'reference' => 'ITSAP.100.49',
            'version' => '1.0',
            'published_at' => today()->subMonth(),
            'url' => 'https://www.cyber.gc.ca/en/guidance/top-10-artificial-intelligence-security-actions-primer-itsap10049',
            'licence' => 'Open Government Licence – Canada',
            'status' => GuidanceStatus::New,
            'owner_id' => User::factory(),
            'applicability' => 'Applies to teams using generative AI tools and agents.',
            'summary' => 'Ten actions to reduce AI-specific security risk.',
            'reviewed_at' => null,
            'next_review_at' => today()->addMonth(),
            'notes' => null,
        ];
    }

    public function adopted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GuidanceStatus::Adopted,
            'reviewed_at' => today(),
        ]);
    }

    public function reviewDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_review_at' => today()->subDay(),
        ]);
    }
}
