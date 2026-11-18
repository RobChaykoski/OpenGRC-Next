<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentClassification;
use App\Enums\DocumentTemplateStatus;
use App\Enums\DocumentTemplateType;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function extracted_text_is_encrypted_at_rest(): void
    {
        $template = $this->template(DocumentClassification::Internal, 'Internal Scope');

        $raw = DB::table('document_templates')->where('id', $template->getKey())->value('body_text');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('Internal Scope', $raw);
        $this->assertSame('Internal clause', $template->fresh()?->body_text);
    }

    #[Test]
    public function confidential_templates_are_visible_only_to_writers(): void
    {
        $internal = $this->template(DocumentClassification::Internal, 'Internal Scope');
        $confidential = $this->template(DocumentClassification::Confidential, 'Confidential NDA');

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->assertTrue($viewer->can('view', $internal));
        $this->assertFalse($viewer->can('view', $confidential));
        $this->assertTrue($editor->can('view', $confidential));
        $this->assertFalse($viewer->can('create', DocumentTemplate::class));
    }

    #[Test]
    public function a_viewer_does_not_see_confidential_templates_in_the_list(): void
    {
        $internal = $this->template(DocumentClassification::Internal, 'Internal Scope');
        $confidential = $this->template(DocumentClassification::Confidential, 'Confidential NDA');

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(DocumentTemplateResource::getUrl('index'))
            ->assertOk()
            ->assertSee($internal->name)
            ->assertDontSee($confidential->name);
    }

    #[Test]
    public function an_editor_can_open_the_template_library(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)->get(DocumentTemplateResource::getUrl('index'))->assertOk();
        $this->actingAs($editor)->get(DocumentTemplateResource::getUrl('create'))->assertOk();
    }

    #[Test]
    public function a_viewer_cannot_open_the_create_page(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)->get(DocumentTemplateResource::getUrl('create'))->assertForbidden();
    }

    #[Test]
    public function a_viewer_cannot_open_a_confidential_template(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $template = $this->template(DocumentClassification::Confidential, 'Confidential NDA');

        $response = $this->actingAs($viewer)
            ->get(DocumentTemplateResource::getUrl('view', ['record' => $template]));

        $this->assertContains($response->status(), [403, 404]);
    }

    private function template(DocumentClassification $classification, string $name): DocumentTemplate
    {
        return DocumentTemplate::create([
            'name' => $name,
            'type' => DocumentTemplateType::Scope,
            'version' => '1.0',
            'status' => DocumentTemplateStatus::Draft,
            'classification' => $classification,
            'body_text' => 'Internal clause',
            'placeholders' => [],
            'outline' => [],
        ]);
    }
}
