<?php

namespace Tests\Feature;

use App\Enums\ControlAttestationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MyAttestations;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\ControlAttestation;
use App\Models\Implementation;
use App\Models\User;
use App\Services\Controls\ControlAttestationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControlAttestationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_request_is_idempotent_while_one_is_pending(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Editor]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $implementation = Implementation::factory()->create([
            'title' => 'Quarterly access review',
            'implementation_owner_id' => $owner->getKey(),
        ]);

        $service = app(ControlAttestationService::class);
        $first = $service->request($implementation, $admin, today()->addDays(7), 'Confirm Q4 access review.');
        $second = $service->request($implementation, $admin);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, $implementation->attestations()->count());
        $this->assertSame(ControlAttestationStatus::Pending, $first->status);
        $this->assertSame(7, (int) today()->diffInDays($first->due_at));
    }

    #[Test]
    public function the_owner_can_attest_and_the_statement_is_kept(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Editor]);
        $implementation = Implementation::factory()->create([
            'implementation_owner_id' => $owner->getKey(),
        ]);
        $attestation = app(ControlAttestationService::class)->request($implementation);

        $result = app(ControlAttestationService::class)->attest(
            $attestation,
            $owner,
            'Confirmed. The review ran on 2 October and the exceptions are tracked.',
        );

        $this->assertSame(ControlAttestationStatus::Attested, $result->status);
        $this->assertSame($owner->getKey(), $result->attested_by);
        $this->assertSame('Confirmed. The review ran on 2 October and the exceptions are tracked.', $result->statement);
        $this->assertNotNull($result->attested_at);
    }

    #[Test]
    public function a_person_who_is_not_the_owner_or_an_admin_cannot_attest(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Editor]);
        $other = User::factory()->create(['role' => UserRole::Editor]);
        $implementation = Implementation::factory()->create([
            'implementation_owner_id' => $owner->getKey(),
        ]);
        $attestation = app(ControlAttestationService::class)->request($implementation);

        $this->expectException(InvalidArgumentException::class);

        app(ControlAttestationService::class)->attest($attestation, $other, 'Looks fine.');
    }

    #[Test]
    public function the_owner_can_decline(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Editor]);
        $implementation = Implementation::factory()->create([
            'implementation_owner_id' => $owner->getKey(),
        ]);
        $attestation = app(ControlAttestationService::class)->request($implementation);

        $result = app(ControlAttestationService::class)->decline($attestation, $owner, 'The review did not run this quarter.');

        $this->assertSame(ControlAttestationStatus::Declined, $result->status);
        $this->assertSame('The review did not run this quarter.', $result->statement);
    }

    #[Test]
    public function the_expiry_command_only_expires_overdue_pending_requests(): void
    {
        $overdue = ControlAttestation::factory()->overdue()->create();
        $future = ControlAttestation::factory()->create();
        $attested = ControlAttestation::factory()->attested()->create(['due_at' => today()->subDays(10)]);

        $this->artisan('opengrc:controls:attestations:expire')
            ->expectsOutputToContain('Expired 1 control attestation')
            ->assertSuccessful();

        $this->assertSame(ControlAttestationStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(ControlAttestationStatus::Pending, $future->fresh()->status);
        $this->assertSame(ControlAttestationStatus::Attested, $attested->fresh()->status);
    }

    #[Test]
    public function the_owners_queue_shows_only_their_pending_attestations(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $owner = User::factory()->create(['role' => UserRole::Editor]);
        $other = User::factory()->create(['role' => UserRole::Editor]);

        $implementation = Implementation::factory()->create([
            'title' => 'Quarterly access review',
            'implementation_owner_id' => $owner->getKey(),
        ]);

        ControlAttestation::factory()->create([
            'implementation_id' => $implementation->getKey(),
            'request_note' => 'Confirm the Q4 access review.',
        ]);

        $this->actingAs($owner)
            ->get(MyAttestations::getUrl())
            ->assertOk()
            ->assertSee('Quarterly access review')
            ->assertSee('Confirm the Q4 access review.');

        $this->actingAs($other)
            ->get(MyAttestations::getUrl())
            ->assertOk()
            ->assertDontSee('Quarterly access review');
    }
}
