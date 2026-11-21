<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\AuditResource\Pages\ViewAudit;
use App\Filament\Resources\AuditResource\RelationManagers\PortalAccessRelationManager;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Audit;
use App\Models\AuditItem;
use App\Models\AuditPortalGrant;
use App\Models\Control;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\Finding;
use App\Models\Implementation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_valid_link_shows_the_audit_and_its_evidence_metadata(): void
    {
        [$audit, $control] = $this->auditWithControl();

        $source = EvidenceSource::create([
            'name' => 'Wazuh',
            'type' => 'wazuh',
            'enabled' => true,
        ]);

        $record = EvidenceRecord::create([
            'source_id' => $source->id,
            'external_id' => 'agent-1',
            'control_id' => $control->id,
            'evidence_type' => 'agent_health',
            'status' => 'effective',
            'collected_at' => now()->subHour(),
            'payload' => ['hostname' => 'secret-host-abc'],
            'payload_sha256' => hash('sha256', 'secret-host-abc'),
            'idempotency_key' => hash('sha256', 'agent-1'),
            'created_at' => now(),
        ]);

        Finding::create([
            'title' => 'MFA not enforced for contractors',
            'description' => 'Contractor accounts bypass the MFA policy.',
            'severity' => 'high',
            'status' => 'open',
            'source' => 'manual',
            'control_id' => $control->id,
            'identified_at' => now(),
        ]);

        $grant = AuditPortalGrant::issue($audit, 'Northwind Assurance', now()->addDays(30));
        $token = $grant->plainToken;

        $this->get(route('audit-portal.show', $token))
            ->assertOk()
            ->assertSee($audit->title)
            ->assertSee($control->code)
            ->assertSee($control->title)
            ->assertSee('Northwind Assurance')
            ->assertSee(substr($record->payload_sha256, 0, 12))
            ->assertSee('F-')
            ->assertSee('MFA not enforced for contractors')
            // The measurement itself, and the licensed control text, stay out.
            ->assertDontSee('secret-host-abc')
            ->assertDontSee('LICENSED-CONTROL-TEXT')
            ->assertDontSee(route('filament.app.resources.controls.view', ['record' => $control->id]));
    }

    #[Test]
    public function an_implementation_item_shows_the_organization_written_details(): void
    {
        $audit = Audit::factory()->create();
        $implementation = Implementation::factory()->create([
            'code' => 'IMP-1',
            'title' => 'Single sign-on',
            'details' => 'We use the corporate identity provider.',
        ]);

        AuditItem::factory()->create([
            'audit_id' => $audit->id,
            'auditable_id' => $implementation->id,
            'auditable_type' => Implementation::class,
        ]);

        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));

        $this->get(route('audit-portal.show', $grant->plainToken))
            ->assertOk()
            ->assertSee('We use the corporate identity provider.')
            ->assertSee('IMP-1');
    }

    #[Test]
    public function a_successful_read_is_recorded_against_the_grant(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));

        $this->get(route('audit-portal.show', $grant->plainToken))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->assertDatabaseHas('audit_portal_views', [
            'audit_portal_grant_id' => $grant->id,
        ]);

        $this->assertSame(1, $grant->fresh()->access_count);
        $this->assertNotNull($grant->fresh()->last_accessed_at);
    }

    #[Test]
    public function an_expired_link_is_rejected_and_not_logged_as_a_view(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->subMinute());

        $this->get(route('audit-portal.show', $grant->plainToken))
            ->assertStatus(410)
            ->assertSee('This link is no longer active')
            ->assertDontSee($audit->title);

        $this->assertDatabaseCount('audit_portal_views', 0);
    }

    #[Test]
    public function a_revoked_link_is_rejected(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));
        $grant->revoke(User::factory()->create());

        $this->get(route('audit-portal.show', $grant->plainToken))
            ->assertStatus(410)
            ->assertDontSee($audit->title);
    }

    #[Test]
    public function the_raw_token_is_never_stored(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));
        $token = $grant->plainToken;

        $this->assertDatabaseMissing('audit_portal_grants', ['token_hash' => $token]);
        $this->assertSame(AuditPortalGrant::hashToken($token), $grant->fresh()->token_hash);
        $this->assertArrayNotHasKey('plainToken', $grant->fresh()->toArray());
    }

    #[Test]
    public function rotating_a_link_invalidates_the_previous_token(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));
        $oldToken = $grant->plainToken;

        $newToken = $grant->rotate('Auditor', now()->addDays(30));

        $this->get(route('audit-portal.show', $oldToken))->assertNotFound();
        $this->get(route('audit-portal.show', $newToken))->assertOk();
    }

    #[Test]
    public function an_unknown_token_is_not_found(): void
    {
        $this->get(route('audit-portal.show', str_repeat('a', 64)))->assertNotFound();
    }

    #[Test]
    public function an_admin_can_issue_a_link_from_the_audit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $audit = Audit::factory()->withManager($admin)->create();

        $this->actingAs($admin);

        $component = Livewire::test(PortalAccessRelationManager::class, [
            'ownerRecord' => $audit,
            'pageClass' => ViewAudit::class,
        ])
            ->assertTableActionVisible('invite')
            ->mountTableAction('invite')
            ->set('mountedActions.0.data.label', 'Northwind Assurance')
            ->set('mountedActions.0.data.expires_in_days', 14)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertTableActionVisible('showIssuedLink');

        $this->assertNotNull($component->get('issuedLink'));
        $this->assertStringContainsString('/audit-portal/', $component->get('issuedLink'));

        $this->assertDatabaseHas('audit_portal_grants', [
            'audit_id' => $audit->id,
            'label' => 'Northwind Assurance',
        ]);

        $grant = AuditPortalGrant::query()->firstOrFail();
        $this->assertTrue($grant->expires_at->between(now()->addDays(13), now()->addDays(15)));
    }

    #[Test]
    public function a_viewer_cannot_issue_a_link(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $audit = Audit::factory()->create();

        $this->actingAs($viewer);

        Livewire::test(PortalAccessRelationManager::class, [
            'ownerRecord' => $audit,
            'pageClass' => ViewAudit::class,
        ])->assertTableActionHidden('invite');

        $this->assertDatabaseCount('audit_portal_grants', 0);
    }

    #[Test]
    public function resolved_findings_are_not_shown_in_the_open_findings_section(): void
    {
        [$audit, $control] = $this->auditWithControl();

        Finding::create([
            'title' => 'Old resolved issue',
            'description' => 'Fixed last quarter.',
            'severity' => 'medium',
            'status' => 'resolved',
            'source' => 'manual',
            'control_id' => $control->id,
            'identified_at' => now()->subMonth(),
            'resolved_at' => now()->subWeek(),
        ]);

        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));

        $this->get(route('audit-portal.show', $grant->plainToken))
            ->assertOk()
            ->assertDontSee('Old resolved issue');
    }

    #[Test]
    public function deleting_the_audit_removes_its_grants_and_views(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Auditor', now()->addDays(30));

        $this->get(route('audit-portal.show', $grant->plainToken))->assertOk();

        $audit->delete();

        $this->assertDatabaseCount('audit_portal_grants', 0);
        $this->assertDatabaseCount('audit_portal_views', 0);
    }

    #[Test]
    public function the_command_lists_and_revokes_a_link(): void
    {
        [$audit] = $this->auditWithControl();
        $grant = AuditPortalGrant::issue($audit, 'Northwind Assurance', now()->addDays(30));

        $this->artisan('opengrc:audits:portal')
            ->expectsOutputToContain('Northwind Assurance')
            ->assertSuccessful();

        $this->artisan('opengrc:audits:portal', ['--revoke' => $grant->id])
            ->expectsOutputToContain("Revoked auditor portal link {$grant->id}")
            ->assertSuccessful();

        $this->assertNotNull($grant->fresh()->revoked_at);
    }

    /**
     * @return array{0: Audit, 1: Control}
     */
    private function auditWithControl(): array
    {
        $audit = Audit::factory()->create(['title' => 'FY26 SOC 2 audit']);
        $control = Control::factory()->create([
            'code' => 'CC6.1',
            'title' => 'Logical access',
            'description' => 'LICENSED-CONTROL-TEXT',
        ]);

        AuditItem::factory()->create([
            'audit_id' => $audit->id,
            'auditable_id' => $control->id,
            'auditable_type' => Control::class,
            'auditor_notes' => '<p>Access review completed.</p>',
        ]);

        return [$audit, $control];
    }
}
