<?php

namespace Tests\Feature;

use App\Models\Implementation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControlTestingCadenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_control_with_a_past_next_test_date_is_overdue(): void
    {
        $implementation = $this->implementation([
            'last_tested_at' => now()->subYear(),
            'next_test_at' => now()->subDay(),
        ]);

        $this->assertTrue($implementation->isTestOverdue());
        $this->assertFalse($implementation->isTestDueSoon());
        $this->assertTrue(Implementation::query()->testOverdue()->whereKey($implementation->id)->exists());
    }

    #[Test]
    public function a_control_due_within_a_month_is_due_soon_but_not_overdue(): void
    {
        $implementation = $this->implementation([
            'next_test_at' => now()->addDays(10),
        ]);

        $this->assertFalse($implementation->isTestOverdue());
        $this->assertTrue($implementation->isTestDueSoon());
    }

    #[Test]
    public function a_control_without_a_schedule_is_not_treated_as_overdue(): void
    {
        $implementation = $this->implementation([]);

        $this->assertFalse($implementation->isTestOverdue());
        $this->assertFalse($implementation->isTestDueSoon());
        $this->assertFalse(Implementation::query()->testOverdue()->whereKey($implementation->id)->exists());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function implementation(array $attributes): Implementation
    {
        return Implementation::create(array_merge([
            'title' => 'Access reviews are performed quarterly',
            'details' => 'The identity team reviews privileged access each quarter.',
        ], $attributes));
    }
}
