<?php

namespace Tests\Feature\Ai;

use App\Enums\AiProvider;
use App\Exceptions\DlpBlockedException;
use App\Services\Ai\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OllamaProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.provider' => 'ollama',
            'ai.model' => null,
            'ai.ollama_url' => 'http://ollama.test:11434',
            'ai.ollama_model' => 'llama3.1',
            'ai.keys' => [],
            'dlp.presidio_url' => null,
        ]);
    }

    #[Test]
    public function a_local_model_needs_no_api_key_and_uses_the_configured_endpoint(): void
    {
        Http::fake([
            'http://ollama.test:11434/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'local ok'],
                ]],
                'model' => 'llama3.1',
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $service = new AiService;

        $this->assertSame(AiProvider::Ollama, $service->getProvider());
        $this->assertSame('llama3.1', $service->getModel());
        $this->assertTrue($service->isConfigured());
        $this->assertSame('http://ollama.test:11434/v1/chat/completions', $service->endpoint());

        $response = $service->chatCompletion(
            'You are a GRC assistant.',
            'Evaluate the access control implementation.',
        );

        $this->assertSame('local ok', $response['content']);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://ollama.test:11434/v1/chat/completions'
            && ! $request->hasHeader('Authorization'));
    }

    #[Test]
    public function the_hr_guard_still_applies_to_local_models(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        try {
            (new AiService)->chatCompletion(
                'You are a GRC assistant.',
                'Employee file: background check and criminal record.',
            );

            $this->fail('The local model call should have been blocked.');
        } catch (DlpBlockedException) {
            // Expected.
        }

        Http::assertNothingSent();
    }
}
