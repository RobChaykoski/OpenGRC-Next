<?php

namespace Tests\Feature;

use App\Enums\NonHumanIdentityStatus;
use App\Models\NonHumanIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NonHumanIdentityReviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_computes_the_next_rotation_from_the_interval(): void
    {
        $identity = NonHumanIdentity::factory()->create([
            'last_rotated_at' => today()->subDays(10),
            'rotation_days' => 30,
            'next_rotation_at' => null,
        ]);

        $this->assertSame(today()->addDays(20)->toDateString(), $identity->next_rotation_at?->toDateString());
    }

    #[Test]
    public function it_marks_expired_and_rotation_due_identities(): void
    {
        $expired = NonHumanIdentity::factory()->create([
            'status' => NonHumanIdentityStatus::Active,
            'expires_at' => today()->subDay(),
        ]);

        $rotation = NonHumanIdentity::factory()->create([
            'status' => NonHumanIdentityStatus::Active,
            'next_rotation_at' => today()->subDay(),
        ]);

        $future = NonHumanIdentity::factory()->create([
            'status' => NonHumanIdentityStatus::Active,
            'next_rotation_at' => today()->addDay(),
            'expires_at' => today()->addMonth(),
        ]);

        $revoked = NonHumanIdentity::factory()->revoked()->create([
            'expires_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:identities:review')->assertSuccessful();

        $this->assertSame(NonHumanIdentityStatus::Expired, $expired->fresh()?->status);
        $this->assertSame(NonHumanIdentityStatus::RotationDue, $rotation->fresh()?->status);
        $this->assertSame(NonHumanIdentityStatus::Active, $future->fresh()?->status);
        $this->assertSame(NonHumanIdentityStatus::Revoked, $revoked->fresh()?->status);
    }

    #[Test]
    public function a_dry_run_does_not_change_a_status(): void
    {
        $expired = NonHumanIdentity::factory()->create([
            'status' => NonHumanIdentityStatus::Active,
            'expires_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:identities:review', ['--dry-run' => true])
            ->expectsOutputToContain('would be marked expired')
            ->assertSuccessful();

        $this->assertSame(NonHumanIdentityStatus::Active, $expired->fresh()?->status);
    }

    #[Test]
    public function an_expired_identity_is_not_downgraded_to_rotation_due(): void
    {
        $identity = NonHumanIdentity::factory()->create([
            'status' => NonHumanIdentityStatus::Active,
            'expires_at' => today()->subDay(),
            'next_rotation_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:identities:review')->assertSuccessful();

        $this->assertSame(NonHumanIdentityStatus::Expired, $identity->fresh()?->status);
    }
}
