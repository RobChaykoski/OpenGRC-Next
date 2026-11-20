<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\AccessReviewResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\AccessReview;
use App\Models\User;
use App\Services\Access\AccessReviewService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_campaign_and_see_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $review = $this->activeReview($admin);

        $this->actingAs($admin)
            ->get(AccessReviewResource::getUrl('index'))
            ->assertOk()
            ->assertSee($review->name)
            ->assertSee('Active');

        $this->actingAs($admin)
            ->get(AccessReviewResource::getUrl('view', ['record' => $review]))
            ->assertOk()
            ->assertSee($review->name);
    }

    #[Test]
    public function a_viewer_can_read_the_campaign_but_not_create_one(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $review = $this->activeReview($viewer);

        $this->actingAs($viewer)
            ->get(AccessReviewResource::getUrl('view', ['record' => $review]))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(AccessReviewResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_open_the_create_form(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(AccessReviewResource::getUrl('create'))
            ->assertOk();
    }

    private function activeReview(User $reviewer): AccessReview
    {
        $review = AccessReview::factory()->create([
            'name' => 'Quarterly access review',
            'reviewer_id' => $reviewer->getKey(),
        ]);

        app(AccessReviewService::class)->start($review, $reviewer);

        return $review;
    }
}
