<?php

namespace Tests\Feature;

use App\Models\DataClassification;
use App\Services\Scoping\DataClassificationCatalog;
use App\Services\Scoping\FrameworkScopingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FrameworkScopingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_canadian_healthcare_profile_selects_phipa_and_phi_controls(): void
    {
        $result = app(FrameworkScopingService::class)->assess([
            'name' => 'Example Hospital',
            'industry' => 'healthcare',
            'country' => 'CA',
            'province' => 'ON',
            'employees' => '250',
            'revenue' => '50m',
            'data_classifications' => ['phi', 'personal_information'],
        ]);

        $this->assertContains('PHIPA (Ontario)', $result['recommended_frameworks']);
        $this->assertContains('PIPEDA', $result['recommended_frameworks']);
        $this->assertNotContains('PCI DSS', $result['recommended_frameworks']);
        $this->assertContains('phi', array_column($result['data_classifications'], 'code'));
        $this->assertNotEmpty($result['controls']);
        $this->assertNotEmpty($result['documents']);
        $this->assertSame([], $result['gaps']);
    }

    #[Test]
    public function a_us_financial_profile_selects_cardholder_controls_without_phipa(): void
    {
        $result = app(FrameworkScopingService::class)->assess([
            'name' => 'Example Bank',
            'industry' => 'financial_services',
            'country' => 'US',
            'state' => 'NY',
            'employees' => '5000',
            'revenue' => '1b',
            'data_classifications' => ['cardholder_data', 'financial_records', 'kyc_records'],
        ]);

        $this->assertContains('PCI DSS', $result['recommended_frameworks']);
        $this->assertContains('SOC 2 (TSC 2017)', $result['recommended_frameworks']);
        $this->assertNotContains('PHIPA (Ontario)', $result['recommended_frameworks']);
        $this->assertNotContains('HIPAA Security Rule', $result['recommended_frameworks']);
        $this->assertContains('cardholder_data', array_column($result['data_classifications'], 'code'));
    }

    #[Test]
    public function custom_industry_classifications_are_available(): void
    {
        DataClassification::create([
            'code' => 'participant_data',
            'label' => 'Research Participant Data',
            'level' => 'restricted',
            'description' => 'Participant-linked research records.',
            'industries' => ['healthcare'],
            'is_system' => false,
        ]);

        $catalog = app(DataClassificationCatalog::class);

        $this->assertArrayHasKey('participant_data', $catalog->options('healthcare'));
        $this->assertArrayNotHasKey('participant_data', $catalog->options('retail'));

        $result = app(FrameworkScopingService::class)->assess([
            'industry' => 'healthcare',
            'country' => 'CA',
            'province' => 'ON',
            'data_classifications' => ['participant_data', 'phi'],
        ]);

        $this->assertContains('participant_data', array_column($result['data_classifications'], 'code'));
    }

    #[Test]
    public function an_unknown_classification_is_reported_as_a_gap(): void
    {
        $result = app(FrameworkScopingService::class)->assess([
            'industry' => 'retail',
            'country' => 'CA',
            'data_classifications' => ['made_up_label'],
        ]);

        $this->assertNotEmpty($result['gaps']);
        $this->assertStringContainsString('made_up_label', implode(' ', $result['gaps']));
    }

    #[Test]
    public function the_command_writes_an_assessment_file(): void
    {
        $profile = tempnam(sys_get_temp_dir(), 'scope-profile-');
        $output = tempnam(sys_get_temp_dir(), 'scope-output-');

        File::put($profile, json_encode([
            'industry' => 'education',
            'country' => 'US',
            'data_classifications' => ['student_records'],
        ]));

        $this->artisan('opengrc:scope', ['profile' => $profile, '--output' => $output])
            ->assertSuccessful();

        $result = json_decode(File::get($output), true);

        $this->assertIsArray($result);
        $this->assertContains('FERPA', $result['recommended_frameworks'] ?? []);

        File::delete([$profile, $output]);
    }
}
