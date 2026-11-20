<?php

namespace Database\Factories;

use App\Enums\AttestationOutcome;
use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use App\Models\AttestationReport;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttestationReport>
 */
class AttestationReportFactory extends Factory
{
    protected $model = AttestationReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issued = $this->faker->dateTimeBetween('-10 months', '-1 month');
        $periodEnd = $this->faker->dateTimeBetween($issued, '-1 day');

        return [
            'name' => 'SOC 2 Type 2 report',
            'type' => AttestationType::Soc2Type2,
            'provider' => $this->faker->company().' LLP',
            'subject' => 'Customer platform',
            'framework_code' => 'TSC2017a',
            'vendor_id' => null,
            'owner_id' => User::factory(),
            'period_start' => (clone $issued)->modify('-12 months'),
            'period_end' => $periodEnd,
            'issued_at' => $issued,
            'expires_at' => null,
            'bridge_letter_date' => null,
            'next_review_at' => $this->faker->dateTimeBetween('now', '+9 months'),
            'outcome' => AttestationOutcome::Unqualified,
            'status' => AttestationStatus::Current,
            'summary' => $this->faker->paragraph(),
            'exceptions' => null,
            'document_reference' => 'vault://assurance/'.$this->faker->uuid(),
            'document_sha256' => hash('sha256', $this->faker->uuid()),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
            'status' => AttestationStatus::Current,
        ]);
    }

    public function vendor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vendor_id' => Vendor::factory(),
        ]);
    }

    public function iso27001(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'ISO/IEC 27001 certificate',
            'type' => AttestationType::Iso27001,
            'framework_code' => 'ISO-27001',
        ]);
    }

    public function qualified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => AttestationOutcome::Qualified,
        ]);
    }
}
