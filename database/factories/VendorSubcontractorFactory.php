<?php

namespace Database\Factories;

use App\Enums\VendorRiskRating;
use App\Models\Vendor;
use App\Models\VendorSubcontractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorSubcontractor>
 */
class VendorSubcontractorFactory extends Factory
{
    protected $model = VendorSubcontractor::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'name' => $this->faker->company(),
            'service' => $this->faker->randomElement(['Hosting', 'Support desk', 'Payment processing', 'Analytics']),
            'country' => $this->faker->countryCode(),
            'accesses_personal_data' => $this->faker->boolean(),
            'risk_rating' => $this->faker->randomElement(VendorRiskRating::cases()),
            'last_reviewed_at' => $this->faker->dateTimeBetween('-18 months', '-1 month'),
            'notes' => $this->faker->sentence(),
        ];
    }
}
