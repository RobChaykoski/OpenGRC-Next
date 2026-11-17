<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\FileAttachment;
use App\Models\User;
use App\Services\PrivateFileAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PrivateFileAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_reach_private_storage(): void
    {
        $this->get('/app/priv-storage/attachments/secret.pdf')
            ->assertRedirect();
    }

    #[Test]
    public function a_user_without_permissions_is_denied(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('attachments/secret.pdf', 'content');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/priv-storage/attachments/secret.pdf')
            ->assertForbidden();
    }

    #[Test]
    public function a_super_admin_can_download_an_allowed_file(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('attachments/secret.pdf', 'content');

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));

        $this->actingAs($user)
            ->get('/app/priv-storage/attachments/secret.pdf')
            ->assertOk();
    }

    #[Test]
    public function paths_outside_the_allowlist_are_rejected(): void
    {
        $this->expectException(HttpException::class);

        app(PrivateFileAccess::class)->assertSafePath('vendor-documents/secret.pdf');
    }

    #[Test]
    public function traversal_paths_are_rejected(): void
    {
        $this->expectException(HttpException::class);

        app(PrivateFileAccess::class)->assertSafePath('attachments/../../.env');
    }

    #[Test]
    public function an_attachment_record_is_authorized_through_its_policy(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('attachments/evidence.pdf', 'content');

        $attachment = FileAttachment::create([
            'file_name' => 'evidence.pdf',
            'file_path' => 'attachments/evidence.pdf',
            'file_size' => 7,
            'description' => 'Test evidence',
            'uploaded_by' => User::factory()->create()->id,
            'audit_id' => Audit::factory()->create()->id,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/priv-storage/'.$attachment->file_path)
            ->assertForbidden();
    }
}
