<?php

namespace Tests\Feature\Scoping;

use App\Enums\UserRole;
use App\Filament\Pages\OrganizationScope;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\DataClassification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationScopePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function a_viewer_can_open_the_scoping_page(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(OrganizationScope::getUrl())
            ->assertOk()
            ->assertSee(__('scoping.title'))
            ->assertSee(__('scoping.form.industry'));
    }

    #[Test]
    public function a_canadian_healthcare_profile_recommends_phipa_and_pipeda(): void
    {
        $this->classifications();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(OrganizationScope::class)
            ->set('data.industry', 'healthcare')
            ->set('data.country', 'CA')
            ->set('data.province', 'CA-ON')
            ->set('data.employees', '250-999')
            ->set('data.revenue', '50m_250m')
            ->set('data.data_classifications', ['phi', 'personal_information'])
            ->call('assess')
            ->assertHasNoErrors()
            ->assertSee('PHIPA (Ontario)')
            ->assertSee('PIPEDA')
            ->assertSee('CTRL-PRIV-001');
    }

    #[Test]
    public function a_us_financial_profile_recommends_pci_without_phipa(): void
    {
        $this->classifications();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(OrganizationScope::class)
            ->set('data.industry', 'financial_services')
            ->set('data.country', 'US')
            ->set('data.province', 'US-NY')
            ->set('data.employees', '1000+')
            ->set('data.revenue', '250m_plus')
            ->set('data.data_classifications', ['cardholder_data', 'financial_records'])
            ->call('assess')
            ->assertHasNoErrors()
            ->assertSee('PCI DSS')
            ->assertSee('SOC 2')
            ->assertDontSee('PHIPA (Ontario)');
    }

    protected function classifications(): void
    {
        foreach ([
            ['code' => 'phi', 'label' => 'Protected health information', 'level' => 'restricted', 'industries' => ['healthcare']],
            ['code' => 'personal_information', 'label' => 'Personal information', 'level' => 'confidential', 'industries' => null],
            ['code' => 'cardholder_data', 'label' => 'Cardholder data', 'level' => 'restricted', 'industries' => null],
            ['code' => 'financial_records', 'label' => 'Financial records', 'level' => 'confidential', 'industries' => ['financial_services']],
        ] as $classification) {
            DataClassification::firstOrCreate(
                ['code' => $classification['code']],
                $classification + [
                    'description' => 'Test classification.',
                    'is_system' => true,
                ]
            );
        }
    }
}
