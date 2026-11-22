<?php

namespace Tests\Feature\Api;

use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\EvidenceStream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceStreamApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'stream-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['evidence_stream.enabled' => true]);
    }

    #[Test]
    public function it_accepts_a_signed_detection_event(): void
    {
        $this->stream();

        $this->push($this->body())
            ->assertStatus(202)
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(1, EvidenceRecord::query()->count());

        $stream = EvidenceStream::query()->sole();

        $this->assertSame('c-1', $stream->last_cursor);
        $this->assertSame(1, $stream->accepted_count);
        $this->assertNotNull($stream->last_received_at);

        // A push source has no schedule; it must not look like a stale pull source.
        $this->assertFalse(
            EvidenceSource::query()->where('name', 'Stream: acme/edr-alerts')->sole()->enabled
        );
    }

    #[Test]
    public function a_replayed_cursor_returns_the_original_record(): void
    {
        $this->stream();

        $first = $this->push($this->body());
        $second = $this->push($this->body());

        $first->assertStatus(202);
        $second->assertStatus(200)->assertJsonPath('status', 'duplicate');

        $this->assertSame($first->json('evidenceId'), $second->json('evidenceId'));
        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_cursor_reused_with_a_different_payload_is_a_conflict(): void
    {
        $this->stream();

        $this->push($this->body())->assertStatus(202);

        $changed = ['alert' => ['count' => 2, 'severity' => 'high']];

        $this->push($this->body(['evidence' => $changed, 'sha256' => $this->digest($changed)]))
            ->assertStatus(409);

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertSame(1, EvidenceStream::query()->sole()->rejected_count);
    }

    #[Test]
    public function a_bad_signature_is_rejected_and_counted(): void
    {
        $this->stream();

        $this->push($this->body(), secret: 'wrong-secret')->assertStatus(401);

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame('invalid_signature', EvidenceStream::query()->sole()->last_rejection_reason);
    }

    #[Test]
    public function a_stale_timestamp_is_rejected(): void
    {
        $this->stream();

        $this->push($this->body(), timestamp: now()->subHour()->getTimestamp())
            ->assertStatus(401);

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_hash_mismatch_is_rejected(): void
    {
        $this->stream();

        $body = $this->body();
        $body['sha256'] = str_repeat('a', 64);

        $this->push($body)->assertStatus(422);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function an_event_time_outside_the_window_is_flagged_late(): void
    {
        $this->stream();

        $body = $this->body([
            'stream' => [
                'name' => 'edr-alerts',
                'cursor' => 'c-late',
                'eventTime' => now()->subDays(3)->toIso8601ZuluString(),
            ],
        ]);

        $this->push($body)
            ->assertStatus(422)
            ->assertJsonPath('late', true);

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function an_unknown_stream_is_refused(): void
    {
        $this->stream();

        $body = $this->body([
            'stream' => [
                'name' => 'unknown-stream',
                'cursor' => 'c-1',
                'eventTime' => now()->toIso8601ZuluString(),
            ],
        ]);

        $this->push($body)->assertStatus(403);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function an_inactive_stream_is_refused(): void
    {
        $this->stream(['is_active' => false]);

        $this->push($this->body())->assertStatus(403);
    }

    #[Test]
    public function the_endpoint_is_off_by_default(): void
    {
        config(['evidence_stream.enabled' => false]);

        $this->stream();

        $this->push($this->body())->assertStatus(404);
    }

    #[Test]
    public function an_unknown_evidence_type_is_rejected(): void
    {
        $this->stream();

        $body = $this->body(['evidenceType' => 'raw-log-lines']);

        $this->push($body)
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidenceType');
    }

    #[Test]
    public function an_unauthenticated_request_is_refused(): void
    {
        $this->stream();

        $this->postJson('/api/v1/evidence/stream', $this->body())
            ->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function body(array $overrides = []): array
    {
        $evidence = ['alert' => ['count' => 1, 'severity' => 'high']];

        $body = [
            'id' => 'event-1',
            'sha256' => $this->digest($evidence),
            'evidenceType' => 'detection-event',
            'evidence' => $evidence,
            'stream' => [
                'name' => 'edr-alerts',
                'cursor' => 'c-1',
                'eventTime' => now()->toIso8601ZuluString(),
            ],
        ];

        return array_replace_recursive($body, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function stream(array $overrides = []): EvidenceStream
    {
        return EvidenceStream::create(array_merge([
            'client_id' => 'acme',
            'name' => 'edr-alerts',
            'secret' => self::SECRET,
            'is_active' => true,
        ], $overrides));
    }

    protected function push(
        array $body,
        ?int $timestamp = null,
        string $secret = self::SECRET,
    ): TestResponse {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp ??= now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, $secret);

        // call() does not merge default headers, so the server vars are explicit.
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token(),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_OPENGRC_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ];

        return $this->call('POST', '/api/v1/evidence/stream', [], [], [], $server, $json);
    }

    protected function token(): string
    {
        $new = User::factory()->create()->createToken('edr', ['evidence.write']);
        $new->accessToken->forceFill(['client_id' => 'acme'])->save();

        return $new->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function digest(array $payload): string
    {
        $this->sort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sort(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sort($value);
            }
        }
    }
}
