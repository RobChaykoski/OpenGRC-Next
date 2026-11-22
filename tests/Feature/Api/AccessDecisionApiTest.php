<?php

namespace Tests\Feature\Api;

use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use App\Models\AccessDecision;
use App\Models\AccessGrant;
use App\Models\AccessPolicy;
use App\Models\ProtectedResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessDecisionApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_a_default_deny_when_no_policy_matches(): void
    {
        $this->resource();

        $response = $this->ask();

        $response->assertOk()
            ->assertJsonPath('action', 'deny')
            ->assertJsonPath('reason', 'no_policy')
            ->assertJsonPath('ttl_seconds', null)
            ->assertJsonStructure(['action', 'reason', 'ttl_seconds', 'correlation_id', 'policy_version', 'rules_hash']);

        $decision = AccessDecision::query()->sole();

        $this->assertSame('acme-ehr', $decision->pep);
        $this->assertSame('deny', $decision->action->value);
        $this->assertSame($response->json('correlation_id'), $decision->correlation_id);
        $this->assertNotNull($decision->latency_ms);
        $this->assertNotNull($decision->protected_resource_id);
    }

    #[Test]
    public function a_stale_classification_is_masked_not_trusted(): void
    {
        $this->resource(stale: true);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('action', 'mask')
            ->assertJsonPath('reason', 'stale_classification');
    }

    #[Test]
    public function it_returns_an_allow_with_the_matched_policy_version_and_rule_hash(): void
    {
        $this->resource();
        $policy = AccessPolicy::factory()->create();

        $this->ask()
            ->assertOk()
            ->assertJsonPath('action', 'allow')
            ->assertJsonPath('reason', 'policy_match')
            ->assertJsonPath('policy_version', $policy->version)
            ->assertJsonPath('rules_hash', $policy->rules_hash);

        $this->assertSame($policy->getKey(), AccessDecision::query()->sole()->matched_policy_id);
    }

    #[Test]
    public function an_explicit_deny_wins_over_a_higher_priority_allow(): void
    {
        $this->resource();

        AccessPolicy::factory()->create(['code' => 'ALLOW', 'priority' => 10]);
        AccessPolicy::factory()->deny()->create(['code' => 'DENY', 'priority' => 900]);

        $this->ask()->assertOk()->assertJsonPath('action', 'deny');
    }

    #[Test]
    public function break_glass_is_time_boxed_with_a_capped_ttl(): void
    {
        $this->resource();

        $this->ask([
            'profile' => 'emergency',
            'justification_present' => true,
            'justification' => 'Unconscious patient in the emergency department.',
            'clinical_reason' => 'trauma',
        ])
            ->assertOk()
            ->assertJsonPath('action', 'time_boxed')
            ->assertJsonPath('reason', 'break_glass')
            ->assertJsonPath(
                'ttl_seconds',
                (int) config('access_governance.grants.break_glass_max_minutes') * 60,
            );
    }

    #[Test]
    public function break_glass_is_refused_outside_a_trusted_context(): void
    {
        $this->resource();

        $this->ask([
            'profile' => 'emergency',
            'trust' => 'medium',
            'justification_present' => true,
            'clinical_reason' => 'trauma',
        ])
            ->assertOk()
            ->assertJsonPath('action', 'deny')
            ->assertJsonPath('reason', 'context_insufficient');
    }

    #[Test]
    public function break_glass_mints_the_grant_and_its_privacy_review(): void
    {
        $resource = $this->resource();

        $response = $this->breakGlassAsk()
            ->assertOk()
            ->assertJsonPath('action', 'time_boxed')
            ->assertJsonPath('reason', 'break_glass');

        $decision = AccessDecision::query()->sole();
        $grant = AccessGrant::query()->sole();

        $this->assertSame($grant->getKey(), $decision->access_grant_id);
        $this->assertSame($decision->correlation_id, $grant->correlation_id);
        $this->assertSame($grant->reference(), $response->json('grant_reference'));

        $this->assertSame(AccessGrantState::Active, $grant->state);
        $this->assertTrue($grant->is_break_glass);
        $this->assertTrue($grant->review_required);
        $this->assertSame(PrivacyReviewOutcome::Pending, $grant->review_outcome);
        $this->assertNotNull($grant->review_due_at);
        $this->assertSame($resource->getKey(), $grant->protected_resource_id);
        $this->assertSame('dr.example', $grant->subject_label);
        $this->assertSame('trauma', $grant->clinical_reason);
        $this->assertSame('Unconscious patient in the emergency department.', $grant->justification);
        $this->assertEqualsWithDelta(
            (int) config('access_governance.grants.break_glass_max_minutes'),
            (int) now()->diffInMinutes($grant->expires_at),
            1,
        );
    }

    #[Test]
    public function break_glass_without_a_justification_is_denied_and_mints_nothing(): void
    {
        $this->resource();

        $this->ask([
            'profile' => 'emergency',
            'justification_present' => true,
            'clinical_reason' => 'trauma',
        ])
            ->assertOk()
            ->assertJsonPath('action', 'deny')
            ->assertJsonPath('reason', 'context_insufficient')
            ->assertJsonPath('grant_reference', null);

        $this->assertSame(0, AccessGrant::query()->count());
        $this->assertSame(1, AccessDecision::query()->count());
    }

    #[Test]
    public function a_replayed_break_glass_request_creates_one_grant(): void
    {
        $this->resource();
        $body = $this->breakGlassBody();

        $first = $this->ask(body: $body, headers: ['Idempotency-Key' => 'bg-1']);
        $second = $this->ask(body: $body, headers: ['Idempotency-Key' => 'bg-1']);

        $this->assertSame($first->json('grant_reference'), $second->json('grant_reference'));
        $this->assertSame(1, AccessGrant::query()->count());
        $this->assertSame(1, AccessDecision::query()->count());
    }

    #[Test]
    public function a_second_break_glass_request_uses_the_active_grant(): void
    {
        $this->resource();

        $this->breakGlassAsk()->assertJsonPath('reason', 'break_glass');

        $this->breakGlassAsk()
            ->assertOk()
            ->assertJsonPath('action', 'allow_audited')
            ->assertJsonPath('reason', 'grant_active');

        $this->assertSame(1, AccessGrant::query()->count());
    }

    #[Test]
    public function an_active_grant_returns_the_remaining_ttl(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 21, 9, 0, 0));

        $resource = $this->resource();
        AccessGrant::factory()->active()->create([
            'protected_resource_id' => $resource->getKey(),
            'subject_type' => 'user',
            'subject_label' => 'dr.example',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('action', 'allow_audited')
            ->assertJsonPath('reason', 'grant_active')
            ->assertJsonPath('ttl_seconds', 600);
    }

    #[Test]
    public function a_revoked_or_expired_grant_is_not_an_entitlement(): void
    {
        $resource = $this->resource();

        AccessGrant::factory()->active()->create([
            'protected_resource_id' => $resource->getKey(),
            'subject_label' => 'dr.example',
            'state' => AccessGrantState::Revoked,
        ]);

        $this->ask()->assertOk()->assertJsonPath('reason', 'no_policy');
    }

    #[Test]
    public function an_unregistered_resource_is_an_audited_deny(): void
    {
        $this->ask(['resource' => ['ref' => 'not.registered']])
            ->assertOk()
            ->assertJsonPath('action', 'deny')
            ->assertJsonPath('reason', 'unknown_classification');

        $decision = AccessDecision::query()->sole();

        $this->assertNull($decision->protected_resource_id);
        $this->assertSame('not.registered', $decision->resource_ref);
    }

    #[Test]
    public function it_does_not_store_the_patient_urn(): void
    {
        $this->resource();

        $this->ask([
            'resource' => [
                'ref' => 'clinical.records',
                'urn' => 'ehr://patient/1234',
            ],
        ])->assertOk();

        $decision = AccessDecision::query()->sole();
        $stored = json_encode([$decision->resource_ref, $decision->context], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('patient/1234', $stored);
        $this->assertStringNotContainsString('ehr://', $stored);
    }

    #[Test]
    public function a_replay_with_the_same_key_returns_the_recorded_decision(): void
    {
        $this->resource();
        $body = $this->body();

        $first = $this->ask(body: $body, headers: ['Idempotency-Key' => 'retry-1']);
        $second = $this->ask(body: $body, headers: ['Idempotency-Key' => 'retry-1']);

        $this->assertSame($first->json('correlation_id'), $second->json('correlation_id'));
        $this->assertSame($first->json('action'), $second->json('action'));
        $this->assertSame(1, AccessDecision::query()->count());
    }

    #[Test]
    public function a_reused_key_with_a_different_request_is_rejected(): void
    {
        $this->resource();

        $this->ask(headers: ['Idempotency-Key' => 'retry-2'])->assertOk();

        $this->ask(
            body: $this->body(['subject' => ['type' => 'user', 'label' => 'someone.else']]),
            headers: ['Idempotency-Key' => 'retry-2'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertSame(1, AccessDecision::query()->count());
    }

    #[Test]
    public function an_over_long_idempotency_key_is_rejected(): void
    {
        $this->resource();

        $this->ask(headers: ['Idempotency-Key' => str_repeat('k', 129)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertSame(0, AccessDecision::query()->count());
    }

    #[Test]
    public function the_exposure_ceiling_is_computed_from_the_log_and_cannot_be_asserted(): void
    {
        config(['access_governance.exposure.thresholds.block' => 8]);

        $this->resource();
        AccessPolicy::factory()->create(['conditions' => []]);

        // A caller that claims it has consumed nothing is still measured.
        $this->ask(body: $this->body(['exposure_weight' => 0, 'grant_active' => false]))
            ->assertOk()
            ->assertJsonPath('action', 'allow');

        $this->ask()
            ->assertOk()
            ->assertJsonPath('action', 'deny')
            ->assertJsonPath('reason', 'exposure_threshold');

        $this->assertSame(
            8,
            AccessDecision::query()->orderByDesc('id')->firstOrFail()->context['exposure_weight'],
        );
    }

    #[Test]
    public function a_claimed_exposure_weight_does_not_block_a_clean_subject(): void
    {
        $this->resource();
        AccessPolicy::factory()->create(['conditions' => []]);

        $this->ask(body: $this->body(['exposure_weight' => 1000]))
            ->assertOk()
            ->assertJsonPath('action', 'allow');
    }

    #[Test]
    public function the_response_is_not_cacheable(): void
    {
        $this->resource();

        $response = $this->ask();
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    #[Test]
    public function the_decision_chain_stays_intact_across_requests(): void
    {
        $this->resource();

        $this->ask();
        $this->ask();

        $this->assertSame(2, AccessDecision::query()->count());
        $this->assertNull(AccessDecision::firstBrokenLink());
    }

    #[Test]
    public function a_missing_token_returns_401_json(): void
    {
        $this->postJson('/api/v1/access/decisions', $this->body())
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function a_token_without_the_scope_returns_403(): void
    {
        $this->withToken($this->token(abilities: []))
            ->postJson('/api/v1/access/decisions', $this->body())
            ->assertStatus(403)
            ->assertJsonPath('message', __('access_governance.decision.missing_scope'));
    }

    #[Test]
    public function a_token_without_a_client_returns_403(): void
    {
        $this->withToken($this->token(clientId: null))
            ->postJson('/api/v1/access/decisions', $this->body())
            ->assertStatus(403)
            ->assertJsonPath('message', __('access_governance.decision.no_client'));
    }

    #[Test]
    public function a_browser_session_is_not_an_enforcement_point(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/access/decisions', $this->body())
            ->assertStatus(403)
            ->assertJsonPath('message', __('access_governance.decision.session_not_allowed'));
    }

    #[Test]
    public function it_rejects_an_unknown_profile(): void
    {
        $this->resource();

        $this->ask(['profile' => 'superuser'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('profile');
    }

    #[Test]
    public function it_rejects_control_characters_in_a_subject_label(): void
    {
        $this->resource();

        $this->ask(['subject' => ['type' => 'user', 'label' => "dr.example\nINJECTED"]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject.label');
    }

    #[Test]
    public function it_requires_a_resource_reference(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/access/decisions', [
                'subject' => ['type' => 'user', 'label' => 'dr.example'],
                'profile' => 'standard',
                'trust' => 'high',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('resource.ref');
    }

    /**
     * @return array<string, mixed>
     */
    protected function breakGlassBody(): array
    {
        return [
            'profile' => 'emergency',
            'trust' => 'high',
            'justification_present' => true,
            'justification' => 'Unconscious patient in the emergency department.',
            'clinical_reason' => 'trauma',
        ];
    }

    protected function breakGlassAsk(): TestResponse
    {
        return $this->ask($this->breakGlassBody());
    }

    protected function resource(bool $stale = false): ProtectedResource
    {
        $factory = ProtectedResource::factory();

        return ($stale ? $factory->stale() : $factory)
            ->create(['external_urn' => 'clinical.records']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function body(array $overrides = []): array
    {
        return array_replace_recursive([
            'subject' => [
                'type' => 'user',
                'label' => 'dr.example',
            ],
            'resource' => [
                'ref' => 'clinical.records',
            ],
            'profile' => 'standard',
            'trust' => 'high',
            'purpose_code' => 'care',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    protected function ask(array $body = [], array $headers = []): TestResponse
    {
        $request = $this->withToken($this->token())->withHeaders($headers);

        return $request->postJson('/api/v1/access/decisions', array_replace_recursive($this->body(), $body));
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function token(array $abilities = ['access.decide'], ?string $clientId = 'acme-ehr'): string
    {
        $new = User::factory()->create()->createToken('ehr-gateway', $abilities);

        if ($clientId !== null) {
            $new->accessToken->forceFill(['client_id' => $clientId])->save();
        }

        return $new->plainTextToken;
    }
}
