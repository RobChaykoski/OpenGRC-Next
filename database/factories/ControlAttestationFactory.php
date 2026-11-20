<?php

namespace Database\Factories;

use App\Enums\ControlAttestationStatus;
use App\Models\ControlAttestation;
use App\Models\Implementation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlAttestation>
 */
class ControlAttestationFactory extends Factory
{
    protected $model = ControlAttestation::class;

    public function definition(): array
    {
        return [
            'implementation_id' => Implementation::factory(),
            'requested_at' => now()->subDays(2),
            'due_at' => today()->addDays(12),
            'status' => ControlAttestationStatus::Pending,
            'request_note' => 'Confirm the control is still operating as described.',
        ];
    }

    public function attested(): static
    {
        return $this->state(fn (): array => [
            'status' => ControlAttestationStatus::Attested,
            'attested_at' => now(),
            'statement' => 'Confirmed. The control operates as described.',
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'due_at' => today()->subDays(3),
        ]);
    }
}
