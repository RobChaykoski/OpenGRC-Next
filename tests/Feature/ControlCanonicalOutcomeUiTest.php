<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ControlResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\CanonicalControl;
use App\Models\Control;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The product must say, in the UI, that an unlinked control is a coverage gap
 * to review rather than a failure. Otherwise people invent links to clear it.
 */
class ControlCanonicalOutcomeUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function the_edit_form_explains_that_leaving_the_link_empty_is_valid(): void
    {
        $control = Control::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(ControlResource::getUrl('edit', ['record' => $control]))
            ->assertOk()
            ->assertSee(__('control.form.canonical_outcomes.helper'));
    }

    #[Test]
    public function the_view_page_calls_an_unlinked_control_a_coverage_gap_not_a_failure(): void
    {
        $control = Control::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(ControlResource::getUrl('view', ['record' => $control]))
            ->assertOk()
            ->assertSee(__('control.infolist.canonical_outcomes_empty'));
    }

    #[Test]
    public function the_control_list_marks_unlinked_controls(): void
    {
        Control::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(ControlResource::getUrl('index'))
            ->assertOk()
            ->assertSee(__('control.table.columns.canonical_outcomes_unlinked'));
    }

    #[Test]
    public function a_linked_outcome_is_visible_on_the_view_page(): void
    {
        $outcome = CanonicalControl::create([
            'code' => 'CTRL-ACCESS-001',
            'name' => 'Least-privilege access control',
            'priority' => 1,
        ]);
        $control = Control::factory()->create();
        $control->canonicalOutcomes()->attach($outcome->getKey(), ['source' => 'manual']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(ControlResource::getUrl('view', ['record' => $control]))
            ->assertOk()
            ->assertSee('CTRL-ACCESS-001')
            ->assertDontSee(__('control.infolist.canonical_outcomes_empty'));
    }
}
