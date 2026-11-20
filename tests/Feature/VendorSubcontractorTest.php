<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\VendorResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubcontractor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VendorSubcontractorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_vendor_records_its_subcontractors(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vendor = Vendor::factory()->create(['name' => 'Example Hosting']);

        VendorSubcontractor::factory()->create([
            'vendor_id' => $vendor->getKey(),
            'name' => 'Regional data centre',
            'accesses_personal_data' => true,
            'last_reviewed_at' => today()->subMonths(14),
        ]);

        $this->assertSame(1, $vendor->subcontractors()->count());
        $this->assertTrue($vendor->subcontractors()->firstOrFail()->isReviewOverdue());

        $this->actingAs($admin)
            ->get(VendorResource::getUrl('view', ['record' => $vendor]))
            ->assertOk()
            ->assertSee('Example Hosting');
    }
}
