<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_url_downloads_the_attachment_for_an_authenticated_user(): void
    {
        [$attachment, $user] = $this->attachmentAndUser();

        $url = URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(5),
            ['fileAttachment' => $attachment->id]
        );

        $this->actingAs($user)->get($url)->assertOk();
    }

    #[Test]
    public function an_unsigned_url_is_rejected(): void
    {
        [$attachment, $user] = $this->attachmentAndUser();

        $this->actingAs($user)
            ->get(route('attachments.download', ['fileAttachment' => $attachment->id]))
            ->assertForbidden();
    }

    #[Test]
    public function a_signed_url_still_requires_authentication(): void
    {
        [$attachment] = $this->attachmentAndUser();

        $url = URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(5),
            ['fileAttachment' => $attachment->id]
        );

        $this->get($url)->assertRedirect();
    }

    /**
     * @return array{0: FileAttachment, 1: User}
     */
    protected function attachmentAndUser(): array
    {
        Storage::fake('private');
        Storage::disk('private')->put('attachments/evidence.pdf', 'content');

        $attachment = FileAttachment::create([
            'file_name' => 'evidence.pdf',
            'file_path' => 'attachments/evidence.pdf',
            'file_size' => 7,
            'description' => 'Audit evidence',
            'uploaded_by' => User::factory()->create()->id,
            'audit_id' => Audit::factory()->create()->id,
        ]);

        return [$attachment, User::factory()->create()];
    }
}
