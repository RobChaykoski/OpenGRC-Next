<?php

namespace Tests\Feature\Dlp;

use App\Exceptions\DlpBlockedException;
use App\Models\DlpFinding;
use App\Services\Ai\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiDlpGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.provider' => 'openai',
            'ai.model' => 'gpt-4.1-mini',
            'ai.keys.openai' => 'test-key',
            'dlp.presidio_url' => null,
        ]);
    }

    #[Test]
    public function hr_content_is_blocked_before_any_request_is_sent(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        try {
            (new AiService)->chatCompletion(
                'You are a GRC assistant.',
                'Employee file: the criminal record check is attached.',
            );

            $this->fail('The AI call should have been blocked.');
        } catch (DlpBlockedException) {
            // Expected.
        }

        Http::assertNothingSent();
        $this->assertSame('blocked', DlpFinding::query()->sole()->action);
    }

    #[Test]
    public function clean_content_is_sent_normally(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'ok'],
                ]],
                'model' => 'gpt-4.1-mini',
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $response = (new AiService)->chatCompletion(
            'You are a GRC assistant.',
            'Evaluate the access control implementation.',
        );

        $this->assertSame('ok', $response['content']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/chat/completions');
    }
}
