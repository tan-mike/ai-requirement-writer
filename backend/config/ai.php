<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI Provider (OpenAI-compatible Chat Completions)
|--------------------------------------------------------------------------
|
| The platform talks to any provider that implements the OpenAI Chat
| Completions API (POST {base_url}/chat/completions). Examples:
|
|   Google Gemini  https://generativelanguage.googleapis.com/v1beta/openai
|   OpenAI         https://api.openai.com/v1
|   OpenRouter     https://openrouter.ai/api/v1
|   Groq           https://api.groq.com/openai/v1
|   DeepSeek       https://api.deepseek.com/v1
|   Ollama         http://localhost:11434/v1   (any non-empty API key)
|
| The legacy GEMINI_* variables are still read as fallbacks so existing
| deployments keep working without changes.
*/

return [
    'base_url' => env('AI_BASE_URL') ?: 'https://generativelanguage.googleapis.com/v1beta/openai',

    'api_key' => env('AI_API_KEY') ?: env('GEMINI_API_KEY'),

    'model' => env('AI_MODEL') ?: 'gemma-4-26b-a4b-it',

    // Seconds to wait for a response before timing out.
    'request_timeout' => (int) (env('AI_REQUEST_TIMEOUT') ?: env('GEMINI_REQUEST_TIMEOUT') ?: 120),

    // Ask the provider to report token usage on streamed responses
    // (stream_options.include_usage). Disable for providers that reject it.
    'stream_usage' => (bool) env('AI_STREAM_USAGE', true),

    // When true, requests made with the platform key are throttled.
    'platform_free_tier' => (bool) (env('AI_PLATFORM_FREE_TIER') ?? env('GEMINI_PLATFORM_FREE_TIER', false)),

    /*
    |--------------------------------------------------------------------------
    | Provider presets for personal keys
    |--------------------------------------------------------------------------
    |
    | Shown in the Settings provider dropdown. Picking one fills in the base URL
    | and suggests the default model; users can still type another model name.
    | Users can also pick "Custom" and enter any public https OpenAI-compatible URL.
    |
    | default_provider is used for a personal key with no saved base URL, and
    | its default_model when no model is saved.
    */
    'default_provider' => 'gemini',

    'providers' => [
        'gemini' => [
            'label' => 'Google Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'default_model' => 'gemma-4-26b-a4b-it',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'default_model' => 'openai/gpt-4o-mini',
        ],
        'groq' => [
            'label' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'default_model' => 'llama-3.3-70b-versatile',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com/v1',
            'default_model' => 'deepseek-chat',
        ],
        'mistral' => [
            'label' => 'Mistral',
            'base_url' => 'https://api.mistral.ai/v1',
            'default_model' => 'mistral-small-latest',
        ],
    ],
];
