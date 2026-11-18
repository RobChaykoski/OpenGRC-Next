<?php

namespace App\Services\Ai;

use App\Enums\AiProvider;
use App\Services\Dlp\DlpService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    protected AiProvider $provider;

    protected string $model;

    protected string $apiKey;

    public function __construct()
    {
        $this->initializeProvider();
    }

    protected function initializeProvider(): void
    {
        $this->provider = $this->resolveProvider();
        $this->model = $this->resolveModel();
        $this->apiKey = $this->resolveApiKey();
    }

    protected function resolveProvider(): AiProvider
    {
        $settingProvider = setting('ai.provider');
        if ($settingProvider) {
            return AiProvider::tryFrom($settingProvider) ?? AiProvider::OpenAI;
        }

        $configProvider = config('ai.provider');
        if ($configProvider) {
            return AiProvider::tryFrom($configProvider) ?? AiProvider::OpenAI;
        }

        return AiProvider::OpenAI;
    }

    protected function resolveModel(): string
    {
        if ($this->provider === AiProvider::Ollama) {
            $ollamaModel = setting('ai.ollama_model') ?: config('ai.ollama_model');
            if (filled($ollamaModel)) {
                return $ollamaModel;
            }
        }

        $settingModel = setting('ai.model');
        if ($settingModel) {
            return $settingModel;
        }

        $configModel = config('ai.model');
        if ($configModel) {
            return $configModel;
        }

        return $this->provider->getDefaultModel();
    }

    protected function resolveApiKey(): string
    {
        if (! $this->provider->requiresApiKey()) {
            return '';
        }

        $settingKey = setting($this->provider->getSettingKeyName());
        if (filled($settingKey)) {
            try {
                return Crypt::decryptString($settingKey);
            } catch (\Exception $e) {
                // Fall through to env key
            }
        }

        $envKey = config('ai.keys.'.$this->provider->value);
        if (filled($envKey)) {
            return $envKey;
        }

        return '';
    }

    public function getProvider(): AiProvider
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function isConfigured(): bool
    {
        return $this->provider->requiresApiKey() ? filled($this->apiKey) : true;
    }

    /**
     * Resolve the chat-completions URL. Ollama uses an operator-configured base
     * URL so the same code works for localhost, a LAN host, or a sidecar.
     */
    public function endpoint(): string
    {
        if ($this->provider !== AiProvider::Ollama) {
            return $this->provider->getEndpoint();
        }

        $base = setting('ai.ollama_url') ?: config('ai.ollama_url', 'http://localhost:11434');
        $base = rtrim((string) $base, '/');

        return match (true) {
            str_ends_with($base, '/v1/chat/completions') => $base,
            str_ends_with($base, '/v1') => $base.'/chat/completions',
            default => $base.'/v1/chat/completions',
        };
    }

    /**
     * Send a chat completion request.
     *
     * @return array{content: string, model: string, usage: array}
     *
     * @throws RuntimeException
     */
    public function chatCompletion(string $systemPrompt, string $userPrompt): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI provider is not configured. Please set an API key.');
        }

        // Hard boundary: HR, special-category, and credential content must never
        // leave the application through an AI provider.
        app(DlpService::class)->assertSafeForAi($systemPrompt."\n".$userPrompt);

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        if ($this->provider === AiProvider::OpenAI) {
            $payload['store'] = true;
        }

        $headers = ['Content-Type' => 'application/json'];

        if (filled($this->apiKey)) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $response = Http::withHeaders($headers)
            ->timeout($this->provider === AiProvider::Ollama ? 120 : 60)
            ->post($this->endpoint(), $payload)
            ->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('The AI provider returned an invalid response.');
        }

        return [
            'content' => (string) ($data['choices'][0]['message']['content'] ?? ''),
            'model' => (string) ($data['model'] ?? $this->model),
            'usage' => is_array($data['usage'] ?? null) ? $data['usage'] : [],
        ];
    }
}
