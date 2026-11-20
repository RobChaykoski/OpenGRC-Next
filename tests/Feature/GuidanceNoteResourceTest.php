<?php

namespace Tests\Feature;

use App\Enums\GuidanceStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GuidanceNoteResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Control;
use App\Models\GuidanceNote;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuidanceNoteResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_guidance_register_and_create_a_note(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $note = $this->note();

        $this->actingAs($admin)
            ->get(GuidanceNoteResource::getUrl('index'))
            ->assertOk()
            ->assertSee('ITSAP.100.49')
            ->assertSee('Canadian Centre for Cyber Security');

        $this->actingAs($admin)
            ->get(GuidanceNoteResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_read_the_note_and_its_control_mapping(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $note = $this->note();

        $this->actingAs($viewer)
            ->get(GuidanceNoteResource::getUrl('view', ['record' => $note]))
            ->assertOk()
            ->assertSee('Ten actions to reduce AI-specific security risk.')
            ->assertSee('SI-4');
    }

    #[Test]
    public function a_viewer_cannot_create_a_note(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(GuidanceNoteResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_create_a_note(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(GuidanceNoteResource::getUrl('create'))
            ->assertOk();
    }

    private function note(): GuidanceNote
    {
        $control = Control::factory()->create(['code' => 'SI-4']);

        $note = GuidanceNote::factory()->create([
            'status' => GuidanceStatus::Adopted,
            'reviewed_at' => today(),
        ]);

        $note->controls()->attach($control);

        return $note;
    }
}
