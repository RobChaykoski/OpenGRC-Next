<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI provider and model to use for AI-powered features.
    | The provider can be set to 'openai' or 'digitalocean'.
    |
    */

    'provider' => env('AI_PROVIDER', 'openai'),

    'model' => env('AI_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Local models
    |--------------------------------------------------------------------------
    |
    | Ollama exposes an OpenAI-compatible endpoint. From a container, use
    | http://host.docker.internal:11434 (Docker Desktop) or the host LAN address.
    |
    */

    'ollama_url' => env('OLLAMA_URL', 'http://localhost:11434'),
    'ollama_model' => env('OLLAMA_MODEL', 'llama3.1'),

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for each provider. These are used as fallbacks when the
    | database settings don't have a key configured.
    |
    */

    'keys' => [
        'openai' => env('OPENAI_API_KEY'),
        'digitalocean' => env('DIGITALOCEAN_AI_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota Settings
    |--------------------------------------------------------------------------
    |
    | Daily token limits for AI usage. Set to 0 to disable quotas.
    |
    */

    'quota' => [
        'prompt' => env('AI_PROMPT_QUOTA', 1000000),
        'response' => env('AI_RESPONSE_QUOTA', 1000000),
    ],
];
