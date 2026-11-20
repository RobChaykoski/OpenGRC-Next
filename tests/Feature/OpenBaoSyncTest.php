<?php

namespace Tests\Feature;

use App\Enums\NonHumanIdentityStatus;
use App\Models\NonHumanIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenBaoSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openbao' => [
                'base_url' => 'https://bao.test',
                'token' => 'reader-token',
                'kv_mount' => 'secret',
                'timeout' => 5,
            ],
        ]);
    }

    #[Test]
    public function it_refreshes_rotation_dates_from_metadata(): void
    {
        Http::preventStrayRequests();
        $this->fakeMetadata();

        $identity = NonHumanIdentity::factory()->create([
            'secret_reference' => 'openbao://secret/opengrc/wazuh-api',
            'rotation_days' => 90,
            'last_rotated_at' => today()->subDays(120),
            'next_rotation_at' => today()->subDays(30),
        ]);

        $this->artisan('opengrc:openbao:sync')->assertSuccessful();

        $identity->refresh();

        $this->assertSame('2026-11-20', $identity->last_rotated_at?->toDateString());
        $this->assertSame('2027-02-18', $identity->next_rotation_at?->toDateString());
        $this->assertNotNull($identity->secret_synced_at);
    }

    #[Test]
    public function a_dry_run_does_not_write(): void
    {
        Http::preventStrayRequests();
        $this->fakeMetadata();

        $identity = NonHumanIdentity::factory()->create([
            'secret_reference' => 'openbao://secret/opengrc/wazuh-api',
            'last_rotated_at' => today()->subDays(120),
        ]);

        $this->artisan('opengrc:openbao:sync', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(today()->subDays(120)->toDateString(), $identity->fresh()?->last_rotated_at?->toDateString());
        $this->assertNull($identity->fresh()?->secret_synced_at);
    }

    #[Test]
    public function a_failed_read_leaves_the_record_unchanged(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://bao.test/v1/secret/metadata/opengrc/wazuh-api' => Http::response([], 403),
        ]);

        $identity = NonHumanIdentity::factory()->create([
            'secret_reference' => 'openbao://secret/opengrc/wazuh-api',
            'last_rotated_at' => today()->subDays(120),
        ]);

        $this->artisan('opengrc:openbao:sync')->assertFailed();

        $this->assertSame(today()->subDays(120)->toDateString(), $identity->fresh()?->last_rotated_at?->toDateString());
    }

    #[Test]
    public function identities_without_an_openbao_reference_are_ignored(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        NonHumanIdentity::factory()->create([
            'secret_reference' => 'vault://kv/other',
            'status' => NonHumanIdentityStatus::Active,
        ]);

        $this->artisan('opengrc:openbao:sync')
            ->expectsOutputToContain('Checked')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    private function fakeMetadata(): void
    {
        Http::fake([
            'https://bao.test/v1/secret/metadata/opengrc/wazuh-api' => Http::response([
                'data' => [
                    'current_version' => 3,
                    'created_time' => '2026-11-20T08:00:00Z',
                    'updated_time' => '2026-11-20T08:00:00Z',
                    'versions' => [
                        '3' => ['created_time' => '2026-11-20T08:00:00Z'],
                    ],
                ],
            ]),
        ]);
    }
}
