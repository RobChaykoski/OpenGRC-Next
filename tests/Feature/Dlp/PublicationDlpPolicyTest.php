<?php

namespace Tests\Feature\Dlp;

use App\Enums\DlpPolicyAction;
use App\Models\DlpPolicy;
use Database\Seeders\PublicationDlpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicationDlpPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PublicationDlpSeeder::class);
    }

    #[Test]
    public function publication_policies_are_seeded(): void
    {
        $policy = DlpPolicy::query()->where('code', 'PUB-001')->firstOrFail();

        $this->assertSame(DlpPolicyAction::Block, $policy->action);
        $this->assertTrue($policy->is_active);
        $this->assertContains('repository', $policy->channels);
        $this->assertContains('documentation', $policy->channels);
        $this->assertContains('infrastructure_metadata', $policy->identifier_types);
        $this->assertContains('assessment_answer_key', $policy->identifier_types);
    }

    #[Test]
    public function answer_keys_and_secrets_have_blocking_controls(): void
    {
        $answerKey = DlpPolicy::query()->where('code', 'PUB-002')->firstOrFail();
        $secrets = DlpPolicy::query()->where('code', 'PUB-004')->firstOrFail();

        $this->assertSame(DlpPolicyAction::Block, $answerKey->action);
        $this->assertContains('assessment_answer_key', $answerKey->identifier_types);
        $this->assertSame(DlpPolicyAction::Quarantine, $secrets->action);
        $this->assertContains('private_key', $secrets->identifier_types);
    }

    #[Test]
    public function infrastructure_metadata_is_alerted_for_review(): void
    {
        $policy = DlpPolicy::query()->where('code', 'PUB-003')->firstOrFail();

        $this->assertSame(DlpPolicyAction::Alert, $policy->action);
        $this->assertContains('infrastructure_metadata', $policy->identifier_types);
    }

    #[Test]
    public function publication_data_classifications_are_available(): void
    {
        $this->assertDatabaseHas('data_classifications', [
            'code' => 'infrastructure_metadata',
            'level' => 'confidential',
        ]);
        $this->assertDatabaseHas('data_classifications', [
            'code' => 'assessment_answer_key',
            'level' => 'restricted',
        ]);
    }
}
