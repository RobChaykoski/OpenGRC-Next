<?php

namespace Tests\Feature;

use App\Enums\RemediationTaskStatus;
use App\Enums\UserRole;
use App\Filament\Resources\RemediationTaskResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\RemediationTask;
use App\Models\Risk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemediationTaskTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_overdue_task_is_open_and_past_its_due_date(): void
    {
        $overdue = RemediationTask::factory()->overdue()->create();
        RemediationTask::factory()->done()->create(['due_at' => today()->subDays(10)]);
        RemediationTask::factory()->create([
            'status' => RemediationTaskStatus::Open,
            'due_at' => today()->addDays(5),
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertSame(1, RemediationTask::query()->overdue()->count());
    }

    #[Test]
    public function completing_a_task_stamps_the_completion_time_and_reopening_clears_it(): void
    {
        $task = RemediationTask::factory()->create([
            'status' => RemediationTaskStatus::Open,
            'completed_at' => null,
        ]);

        $task->update(['status' => RemediationTaskStatus::Done]);
        $this->assertNotNull($task->fresh()->completed_at);

        $task->update(['status' => RemediationTaskStatus::InProgress]);
        $this->assertNull($task->fresh()->completed_at);
    }

    #[Test]
    public function an_admin_can_open_the_register_and_a_viewer_can_read_but_not_create(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $risk = Risk::factory()->create(['name' => 'Unpatched public service']);

        $task = RemediationTask::factory()->create([
            'risk_id' => $risk->getKey(),
            'title' => 'Patch the public service',
            'status' => RemediationTaskStatus::InProgress,
            'due_at' => today()->addDays(7),
        ]);

        $this->actingAs($admin)
            ->get(RemediationTaskResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Patch the public service');

        $this->actingAs($admin)
            ->get(RemediationTaskResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(RemediationTaskResource::getUrl('view', ['record' => $task]))
            ->assertOk()
            ->assertSee('Unpatched public service');

        $this->actingAs($viewer)
            ->get(RemediationTaskResource::getUrl('create'))
            ->assertForbidden();
    }
}
