<?php

namespace Tests\Feature;

use App\Enums\CrosswalkConfidence;
use App\Enums\CrosswalkStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ControlCrosswalkResource;
use App\Filament\Resources\ControlResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Models\Standard;
use App\Models\User;
use App\Services\Controls\ControlCrosswalkService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControlCrosswalkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_crosswalk_connects_two_controls_from_different_standards(): void
    {
        [$source, $target] = $this->controls();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $crosswalk = app(ControlCrosswalkService::class)->create([
            'source_control_id' => (int) $source->getKey(),
            'target_control_id' => (int) $target->getKey(),
            'rationale' => 'Both require a vulnerability management programme.',
            'confidence' => CrosswalkConfidence::High->value,
        ], $admin);

        $this->assertSame(CrosswalkStatus::Draft, $crosswalk->status);
        $this->assertSame($admin->getKey(), $crosswalk->created_by);
        $this->assertSame('XW-'.str_pad((string) $crosswalk->getKey(), 5, '0', STR_PAD_LEFT), $crosswalk->reference());
    }

    #[Test]
    public function it_rejects_the_same_control_or_the_same_standard(): void
    {
        [$source, $target] = $this->controls();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sameStandard = Control::factory()->create(['standard_id' => $source->standard_id]);
        $service = app(ControlCrosswalkService::class);

        try {
            $service->create([
                'source_control_id' => (int) $source->getKey(),
                'target_control_id' => (int) $source->getKey(),
                'rationale' => 'Self.',
                'confidence' => CrosswalkConfidence::High->value,
            ], $admin);
            $this->fail('A self-crosswalk should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $service->create([
            'source_control_id' => (int) $source->getKey(),
            'target_control_id' => (int) $sameStandard->getKey(),
            'rationale' => 'Same framework.',
            'confidence' => CrosswalkConfidence::Medium->value,
        ], $admin);
    }

    #[Test]
    public function the_same_pair_cannot_be_recorded_in_either_direction(): void
    {
        [$source, $target] = $this->controls();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $service = app(ControlCrosswalkService::class);

        $service->create([
            'source_control_id' => (int) $source->getKey(),
            'target_control_id' => (int) $target->getKey(),
            'rationale' => 'First direction.',
            'confidence' => CrosswalkConfidence::High->value,
        ], $admin);

        $this->expectException(InvalidArgumentException::class);
        $service->create([
            'source_control_id' => (int) $target->getKey(),
            'target_control_id' => (int) $source->getKey(),
            'rationale' => 'Reverse.',
            'confidence' => CrosswalkConfidence::High->value,
        ], $admin);
    }

    #[Test]
    public function an_admin_can_approve_or_reject_a_draft(): void
    {
        [$source, $target] = $this->controls();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $service = app(ControlCrosswalkService::class);

        $approved = $service->create([
            'source_control_id' => (int) $source->getKey(),
            'target_control_id' => (int) $target->getKey(),
            'rationale' => 'Approved mapping.',
            'confidence' => CrosswalkConfidence::High->value,
        ], $admin);

        $approved = $service->approve($approved, $admin, 'Reviewed against both texts.');

        $this->assertSame(CrosswalkStatus::Approved, $approved->status);
        $this->assertSame($admin->getKey(), $approved->reviewed_by);
        $this->assertSame('Reviewed against both texts.', $approved->review_note);
        $this->assertNotNull($approved->reviewed_at);

        $rejected = $service->create([
            'source_control_id' => (int) $source->getKey(),
            'target_control_id' => (int) Control::factory()->create([
                'standard_id' => Standard::factory()->create(['code' => 'ISO-27001'])->getKey(),
            ])->getKey(),
            'rationale' => 'A pairing to reject.',
            'confidence' => CrosswalkConfidence::Low->value,
        ], $admin);

        $rejected = $service->reject($rejected, $admin, 'The requirements are not equivalent.');
        $this->assertSame(CrosswalkStatus::Rejected, $rejected->status);
        $this->assertSame('The requirements are not equivalent.', $rejected->review_note);

        $this->expectException(InvalidArgumentException::class);
        $service->reject($approved, $admin, 'Cannot reject an approved mapping.');
    }

    #[Test]
    public function an_admin_can_open_the_register_and_a_viewer_can_read_it(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        [$source, $target] = $this->controls();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $crosswalk = ControlCrosswalk::factory()->approved()->create([
            'source_control_id' => $source->getKey(),
            'target_control_id' => $target->getKey(),
            'rationale' => 'Both require a vulnerability management programme.',
            'reviewed_by' => $admin->getKey(),
        ]);

        $this->actingAs($admin)
            ->get(ControlCrosswalkResource::getUrl('index'))
            ->assertOk()
            ->assertSee($crosswalk->reference());

        $this->actingAs($admin)
            ->get(ControlCrosswalkResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(ControlCrosswalkResource::getUrl('view', ['record' => $crosswalk]))
            ->assertOk()
            ->assertSee('Both require a vulnerability management programme.');

        $this->actingAs($viewer)
            ->get(ControlResource::getUrl('view', ['record' => $source]))
            ->assertOk()
            ->assertSee('Crosswalks')
            ->assertSee('ID.RA-01')
            ->assertSee('Approved');

        $this->actingAs($viewer)
            ->get(ControlCrosswalkResource::getUrl('create'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Control, 1: Control}
     */
    private function controls(): array
    {
        $sourceStandard = Standard::factory()->create(['code' => 'CSCv8']);
        $targetStandard = Standard::factory()->create(['code' => 'NIST-CSF-2.0']);

        $source = Control::factory()->create([
            'standard_id' => $sourceStandard->getKey(),
            'code' => 'CSC-7',
            'title' => 'Continuous vulnerability management',
        ]);

        $target = Control::factory()->create([
            'standard_id' => $targetStandard->getKey(),
            'code' => 'ID.RA-01',
            'title' => 'Vulnerabilities are identified',
        ]);

        return [$source, $target];
    }
}
