<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ImplementationResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\FileAttachment;
use App\Models\Implementation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImplementationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_file_can_be_attached_to_an_implementation_as_evidence(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('implementation-evidence/test.txt', 'operating evidence');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $implementation = Implementation::factory()->create(['title' => 'Quarterly access review']);

        $attachment = FileAttachment::query()->create([
            'implementation_id' => $implementation->getKey(),
            'audit_id' => null,
            'description' => 'Screenshot of the quarterly review.',
            'file_name' => 'review.txt',
            'file_path' => 'implementation-evidence/test.txt',
            'file_size' => 18,
            'uploaded_by' => $admin->getKey(),
        ]);

        $this->assertSame(1, $implementation->attachments()->count());
        $this->assertSame(64, strlen((string) $attachment->fresh()->file_sha256));

        $this->actingAs($admin)
            ->get(ImplementationResource::getUrl('view', ['record' => $implementation]))
            ->assertOk()
            ->assertSee('Quarterly access review');
    }
}
