<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ExerciseResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Exercise;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_exercise_register_and_create_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exercise = Exercise::factory()->completed()->create();

        $this->actingAs($admin)
            ->get(ExerciseResource::getUrl('index'))
            ->assertOk()
            ->assertSee($exercise->name)
            ->assertSee('CISA CTEP');

        $this->actingAs($admin)
            ->get(ExerciseResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_read_an_exercise_but_not_create_one(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $exercise = Exercise::factory()->completed()->create();

        $this->actingAs($viewer)
            ->get(ExerciseResource::getUrl('view', ['record' => $exercise]))
            ->assertOk()
            ->assertSee('Escalation path was unclear after hours.');

        $this->actingAs($viewer)
            ->get(ExerciseResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_open_the_create_form(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(ExerciseResource::getUrl('create'))
            ->assertOk();
    }
}
