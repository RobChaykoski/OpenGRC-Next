<?php

namespace Database\Factories;

use App\Enums\ExerciseSource;
use App\Enums\ExerciseStatus;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ransomware tabletop',
            'type' => ExerciseType::Tabletop,
            'source' => ExerciseSource::CisaCtep,
            'source_reference' => 'CTEP-Ransomware',
            'scenario' => 'A ransomware incident affects a core service.',
            'objectives' => 'Test escalation, communications and recovery decisions.',
            'scope' => 'Incident response team and service owners.',
            'scheduled_at' => today()->addWeek(),
            'conducted_at' => null,
            'status' => ExerciseStatus::Planned,
            'facilitator_id' => User::factory(),
            'participants' => ['Incident response', 'Legal', 'Communications'],
            'findings_summary' => null,
            'corrective_actions' => null,
            'evidence_reference' => null,
            'evidence_sha256' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExerciseStatus::Completed,
            'conducted_at' => today(),
            'findings_summary' => 'Escalation path was unclear after hours.',
            'corrective_actions' => 'Update the on-call contact list and test it next quarter.',
            'evidence_reference' => 'vault://exercises/ransomware-tabletop',
            'evidence_sha256' => hash('sha256', 'ransomware-tabletop'),
        ]);
    }
}
