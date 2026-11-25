<?php

namespace Tests\Feature;

use App\Enums\ModelArtifactFormat;
use App\Enums\ModelArtifactStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ModelArtifactResource;
use App\Filament\Resources\ModelArtifactResource\Pages\ListModelArtifacts;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\ModelArtifact;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelArtifactUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_viewer_can_read_the_register_but_cannot_approve(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $artifact = $this->artifact();

        $this->actingAs($viewer)
            ->get(ModelArtifactResource::getUrl('index'))
            ->assertOk()
            ->assertSee($artifact->name);

        Livewire::test(ListModelArtifacts::class)
            ->assertTableActionHidden('approve', $artifact)
            ->assertTableActionHidden('withdrawApproval', $artifact);

        $this->assertSame(ModelArtifactStatus::Proposed, $artifact->refresh()->status);
    }

    #[Test]
    public function an_editor_can_approve_a_verified_artifact(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $artifact = $this->artifact();

        $this->actingAs($editor);

        Livewire::test(ListModelArtifacts::class)
            ->assertTableActionVisible('approve', $artifact)
            ->callTableAction('approve', $artifact)
            ->assertHasNoTableActionErrors();

        $artifact->refresh();

        $this->assertTrue($artifact->isApproved());
        $this->assertSame($editor->getKey(), $artifact->approved_by);
        $this->assertNotNull($artifact->approved_at);
    }

    #[Test]
    public function a_pickle_or_unhashed_artifact_cannot_be_approved(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $pickle = $this->artifact([
            'name' => 'Pickle Model',
            'format' => ModelArtifactFormat::Pytorch,
        ]);
        $unhashed = $this->artifact([
            'name' => 'Unhashed Model',
            'content_hash' => null,
            'provenance_status' => 'unverified',
        ]);

        $this->actingAs($editor);

        Livewire::test(ListModelArtifacts::class)
            ->assertTableActionHidden('approve', $pickle)
            ->assertTableActionHidden('approve', $unhashed);

        $this->assertFalse($pickle->refresh()->isApproved());
        $this->assertFalse($unhashed->refresh()->isApproved());
    }

    #[Test]
    public function an_editor_can_withdraw_an_approval_and_retire_the_artifact(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $artifact = $this->artifact([
            'status' => ModelArtifactStatus::Approved,
            'approved_by' => $editor->getKey(),
            'approved_at' => now(),
        ]);

        $this->actingAs($editor);

        Livewire::test(ListModelArtifacts::class)
            ->assertTableActionVisible('withdrawApproval', $artifact)
            ->callTableAction('withdrawApproval', $artifact)
            ->assertHasNoTableActionErrors();

        $artifact->refresh();

        $this->assertSame(ModelArtifactStatus::Proposed, $artifact->status);
        $this->assertNull($artifact->approved_by);
        $this->assertNull($artifact->approved_at);

        Livewire::test(ListModelArtifacts::class)
            ->callTableAction('retire', $artifact)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ModelArtifactStatus::Retired, $artifact->refresh()->status);
    }

    #[Test]
    public function the_view_page_shows_the_hash_and_the_handling_class(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $artifact = $this->artifact();

        $this->actingAs($editor)
            ->get(ModelArtifactResource::getUrl('view', ['record' => $artifact]))
            ->assertOk()
            ->assertSee($artifact->name)
            ->assertSee($artifact->content_hash)
            ->assertSee('Preferred training and serving format')
            ->assertSee('apache-2.0');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function artifact(array $overrides = []): ModelArtifact
    {
        return ModelArtifact::create(array_merge([
            'name' => 'Llama Test',
            'source' => 'https://example.test/model',
            'revision' => 'abc123',
            'format' => ModelArtifactFormat::Safetensors,
            'license' => 'apache-2.0',
            'content_hash' => str_repeat('a', 64),
            'status' => ModelArtifactStatus::Proposed,
            'provenance_status' => 'verified',
            'file_count' => 1,
            'total_bytes' => 1024,
        ], $overrides));
    }
}
