<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiProvider: string implements HasLabel
{
    case OpenAI = 'openai';
    case DigitalOcean = 'digitalocean';
    case Ollama = 'ollama';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::DigitalOcean => 'DigitalOcean',
            self::Ollama => 'Ollama (local)',
        };
    }

    public function getEndpoint(): string
    {
        return match ($this) {
            self::OpenAI => 'https://api.openai.com/v1/chat/completions',
            self::DigitalOcean => 'https://inference.do-ai.run/v1/chat/completions',
            self::Ollama => 'http://localhost:11434/v1/chat/completions',
        };
    }

    /**
     * Get available models for this provider.
     *
     * @return array<string, string>
     */
    public function getModels(): array
    {
        return match ($this) {
            self::OpenAI => [
                'gpt-4.1-mini' => 'GPT-4.1 Mini',
                'gpt-4.1' => 'GPT-4.1',
                'gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            ],
            self::DigitalOcean => [
                'alibaba-qwen3-32b' => 'Qwen3 32B',
            ],
            self::Ollama => [
                'llama3.1' => 'Llama 3.1',
                'llama3.2' => 'Llama 3.2',
                'qwen2.5' => 'Qwen 2.5',
                'qwen2.5-coder' => 'Qwen 2.5 Coder',
                'mistral' => 'Mistral',
                'deepseek-r1' => 'DeepSeek R1',
                'phi4' => 'Phi 4',
                'gemma3' => 'Gemma 3',
            ],
        };
    }

    public function getDefaultModel(): string
    {
        return match ($this) {
            self::OpenAI => 'gpt-4.1-mini',
            self::DigitalOcean => 'alibaba-qwen3-32b',
            self::Ollama => 'llama3.1',
        };
    }

    public function getEnvKeyName(): string
    {
        return match ($this) {
            self::OpenAI => 'OPENAI_API_KEY',
            self::DigitalOcean => 'DIGITALOCEAN_AI_KEY',
            self::Ollama => 'OLLAMA_API_KEY',
        };
    }

    public function getSettingKeyName(): string
    {
        return match ($this) {
            self::OpenAI => 'ai.openai_key',
            self::DigitalOcean => 'ai.digitalocean_key',
            self::Ollama => 'ai.ollama_key',
        };
    }

    /**
     * A local Ollama server needs no API key.
     */
    public function requiresApiKey(): bool
    {
        return $this !== self::Ollama;
    }
}
