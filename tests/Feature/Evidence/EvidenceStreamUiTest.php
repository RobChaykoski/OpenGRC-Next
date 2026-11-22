<?php

namespace Tests\Feature\Evidence;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\EvidenceStreamResource\Pages\ListEvidenceStreams;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\EvidenceStream;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceStreamUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_sees_the_stream_counters_but_never_the_secret(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $stream = $this->stream();
        $stream->markAccepted('c-1');
        $stream->markRejected('invalid_signature');

        $this->actingAs($admin)
            ->get('/admin/evidence-streams')
            ->assertOk()
            ->assertSee('acme')
            ->assertSee('edr-alerts')
            ->assertSee('invalid_signature')
            ->assertDontSee('stream-secret');
    }

    #[Test]
    public function an_editor_or_viewer_cannot_open_the_stream_screen(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($editor)
            ->get('/admin/evidence-streams')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get('/admin/evidence-streams')
            ->assertForbidden();
    }

    #[Test]
    public function an_admin_can_disable_and_enable_a_stream(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $stream = $this->stream();

        $this->actingAs($admin);

        Livewire::test(ListEvidenceStreams::class)
            ->callTableAction('disable', $stream)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($stream->refresh()->is_active);

        Livewire::test(ListEvidenceStreams::class)
            ->callTableAction('enable', $stream)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($stream->refresh()->is_active);
    }

    protected function stream(): EvidenceStream
    {
        return EvidenceStream::create([
            'client_id' => 'acme',
            'name' => 'edr-alerts',
            'secret' => 'stream-secret',
            'is_active' => true,
        ]);
    }
}
