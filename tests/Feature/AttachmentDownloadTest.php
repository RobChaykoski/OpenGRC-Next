<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_url_downloads_the_attachment_for_a_permitted_user(): void
    {
        [$attachment, $user] = $this->attachmentAndViewer();

        $this->assertTrue(
            $user->can('view', $attachment),
            'The test user should be authorized to view the attachment before downloading it.'
        );

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
        [$attachment, $user] = $this->attachmentAndViewer();

        $this->actingAs($user)
            ->get(route('attachments.download', ['fileAttachment' => $attachment->id]))
            ->assertForbidden();
    }

    #[Test]
    public function a_signed_url_still_requires_the_read_permission(): void
    {
        [$attachment] = $this->attachmentAndViewer();

        $url = URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(5),
            ['fileAttachment' => $attachment->id]
        );

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get($url)->assertForbidden();
    }

    /**
     * @return array{0: FileAttachment, 1: User}
     */
    protected function attachmentAndViewer(): array
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

        $role = Role::findOrCreate('Evidence Reader', 'web');
        $permission = Permission::findOrCreate('Read FileAttachments', 'web');
        $role->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole($role);

        return [$attachment, $user];
    }
}
