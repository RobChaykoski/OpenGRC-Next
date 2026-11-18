<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSeverity;
use App\Enums\EvidenceSourceType;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ControlResource;
use App\Filament\Resources\EvidenceRecordResource;
use App\Filament\Resources\FindingResource;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\Finding;
use App\Models\User;
use App\Policies\EvidenceRecordPolicy;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceUiAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function a_viewer_can_open_the_findings_queue(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(FindingResource::getUrl('index'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_cannot_open_the_finding_create_page(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(FindingResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_open_the_finding_create_page(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(FindingResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_cannot_edit_a_finding(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(FindingResource::getUrl('edit', ['record' => $this->finding()]))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_edit_a_finding(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(FindingResource::getUrl('edit', ['record' => $this->finding()]))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_open_the_evidence_browser(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(EvidenceRecordResource::getUrl('index'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_inspect_an_evidence_record(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(EvidenceRecordResource::getUrl('view', ['record' => $this->evidenceRecord()]))
            ->assertOk()
            ->assertSee('Payload SHA-256');
    }

    #[Test]
    public function the_evidence_resource_never_offers_writes(): void
    {
        $record = $this->evidenceRecord();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertFalse(EvidenceRecordResource::canCreate());
        $this->assertFalse(EvidenceRecordResource::canEdit($record));
        $this->assertFalse(EvidenceRecordResource::canDelete($record));
        $this->assertFalse((new EvidenceRecordPolicy)->update($admin, $record));
    }

    #[Test]
    public function the_control_page_renders_the_evidence_and_validation_tabs(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $control = Control::factory()->create();

        $this->actingAs($viewer)
            ->get(ControlResource::getUrl('view', ['record' => $control]))
            ->assertOk()
            ->assertSee('Evidence records')
            ->assertSee('Validation history');
    }

    #[Test]
    public function the_dashboard_renders_the_continuous_verification_widget(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('Open findings');
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

    protected function evidenceRecord(): EvidenceRecord
    {
        $source = EvidenceSource::create([
            'name' => 'PatchMon',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
        ]);

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        return app(EvidenceIngestor::class)->ingest($source, $job, new NormalisedEvidence(
            externalId: 'host-1',
            evidenceType: 'patch-compliance',
            status: ControlStatus::Failed,
            collectedAt: CarbonImmutable::parse('2026-11-17T09:00:00Z'),
            payload: ['securityUpdates' => 2],
        ));
    }
}
