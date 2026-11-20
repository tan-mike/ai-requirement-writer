<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\OpenAiCompatibleClient;
use App\Services\AiGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => [
                'free_tier' => $request->user()->free_tier,
                'ai_api_key' => $request->user()->ai_api_key,
                'ai_base_url' => $request->user()->ai_base_url,
                'ai_model' => $request->user()->ai_model,
                'tour_flags' => $request->user()->tour_flags ?? [],
                // The platform base URL may be an internal host, so only the model is shared.
                'platform_model' => config('ai.model'),
                'default_provider' => config('ai.default_provider'),
                'providers' => collect(config('ai.providers', []))
                    ->map(fn (array $p, string $id) => ['id' => $id] + $p)
                    ->values(),
            ]
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'free_tier' => 'nullable|boolean',
            'ai_api_key' => 'nullable|string|max:500',
            'ai_base_url' => ['nullable', ...$this->baseUrlRules()],
            'ai_model' => 'nullable|string|max:255',
            'tour_flags' => 'nullable|array',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'data' => $request->user()->only('free_tier', 'tour_flags'),
            'message' => 'Settings updated successfully.',
        ]);
    }

    /**
     * List the chat models a provider offers for the given (or saved) personal key.
     *
     * POST so an unsaved key travels in the body, not the URL. Results are cached
     * for 10 minutes per base URL and key.
     */
    public function models(Request $request)
    {
        $providers = config('ai.providers', []);

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in([...array_keys($providers), 'custom'])],
            'base_url' => ['required_if:provider,custom', 'nullable', ...$this->baseUrlRules()],
            'api_key' => 'nullable|string|max:500',
        ]);

        $baseUrl = $validated['provider'] === 'custom'
            ? (string) $validated['base_url']
            : (string) $providers[$validated['provider']]['base_url'];
        $apiKey = (string) ($validated['api_key'] ?? $request->user()->ai_api_key ?? '');

        $client = new OpenAiCompatibleClient(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            timeout: 15,
            requirePublicHost: !AiGenerationService::isPresetBaseUrl($baseUrl),
        );

        $cacheKey = 'ai-models:' . hash('sha256', rtrim($baseUrl, '/') . '|' . $apiKey);

        try {
            $models = Cache::remember($cacheKey, now()->addMinutes(10), fn () => $client->listModels());
        } catch (AiProviderException $e) {
            $hint = in_array($e->status, [401, 403], true) && $apiKey === ''
                ? ' Enter your API key for this provider first.'
                : '';

            return response()->json(['message' => 'Could not load models. ' . $e->getMessage() . $hint], 502);
        }

        return response()->json(['data' => $models]);
    }

    /**
     * Rules for a user-supplied provider base URL: https, and a public host unless it is a preset.
     */
    private function baseUrlRules(): array
    {
        return [
            'string',
            'max:500',
            'url:https',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (AiGenerationService::isPresetBaseUrl((string) $value)) {
                    return; // Known provider from config/ai.php
                }
                try {
                    OpenAiCompatibleClient::assertPublicUrl((string) $value);
                } catch (\InvalidArgumentException $e) {
                    $fail($e->getMessage());
                }
            },
        ];
    }
}
