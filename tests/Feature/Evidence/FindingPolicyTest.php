<?php

namespace Tests\Feature\Evidence;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\UserRole;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FindingPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_viewer_can_read_but_not_write_or_accept_risk(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $finding = $this->finding();

        $this->assertTrue($viewer->can('viewAny', Finding::class));
        $this->assertTrue($viewer->can('view', $finding));
        $this->assertFalse($viewer->can('create', Finding::class));
        $this->assertFalse($viewer->can('update', $finding));
        $this->assertFalse($viewer->can('delete', $finding));
        $this->assertFalse($viewer->can('resolve', $finding));
        $this->assertFalse($viewer->can('accept', $finding));
    }

    #[Test]
    public function an_editor_can_resolve_and_accept_but_not_delete(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $finding = $this->finding();

        $this->assertTrue($editor->can('create', Finding::class));
        $this->assertTrue($editor->can('update', $finding));
        $this->assertTrue($editor->can('resolve', $finding));
        $this->assertTrue($editor->can('accept', $finding));
        $this->assertFalse($editor->can('delete', $finding));
    }

    #[Test]
    public function an_admin_can_delete(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $finding = $this->finding();

        $this->assertTrue($admin->can('delete', $finding));
        $this->assertTrue($admin->can('deleteAny', Finding::class));
        $this->assertTrue($admin->can('accept', $finding));
    }

    protected function finding(): Finding
    {
        return Finding::create([
            'title' => 'A gap',
            'description' => 'Condition and recommendation.',
            'severity' => EvidenceSeverity::Medium,
            'status' => FindingStatus::Open,
            'source' => FindingSource::Manual,
            'identified_at' => now(),
        ]);
    }
}
