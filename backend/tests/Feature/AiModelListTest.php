<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\OpenAiCompatibleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiModelListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        OpenAiCompatibleClient::$resolver = null;
        parent::tearDown();
    }

    public function test_lists_chat_models_with_the_key_from_the_request(): void
    {
        Http::fake(['api.openai.com/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'gpt-4o-mini', 'object' => 'model'],
            ['id' => 'text-embedding-3-small', 'object' => 'model'],
            ['id' => 'whisper-1', 'object' => 'model'],
            ['id' => 'gpt-4o', 'object' => 'model'],
            ['id' => 'tts-1', 'object' => 'model'],
            ['id' => 'dall-e-3', 'object' => 'model'],
        ]])]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/user/ai/models', ['provider' => 'openai', 'api_key' => 'sk-typed'])
            ->assertOk()
            ->assertExactJson(['data' => ['gpt-4o', 'gpt-4o-mini']]);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === 'https://api.openai.com/v1/models'
            && $r->hasHeader('Authorization', 'Bearer sk-typed'));
    }

    public function test_falls_back_to_saved_key_and_strips_gemini_prefix(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'models/gemini-2.5-flash', 'object' => 'model'],
            ['id' => 'models/text-embedding-004', 'object' => 'model'],
            ['id' => 'models/gemma-4-26b-a4b-it', 'object' => 'model'],
        ]])]);

        $this->actingAs(User::factory()->create(['ai_api_key' => 'saved-key']))
            ->postJson('/api/user/ai/models', ['provider' => 'gemini'])
            ->assertOk()
            ->assertExactJson(['data' => ['gemini-2.5-flash', 'gemma-4-26b-a4b-it']]);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/models'
            && $r->hasHeader('Authorization', 'Bearer saved-key'));
    }

    public function test_uses_capability_fields_for_mistral_and_openrouter(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['data' => [
                ['id' => 'mistral-small-latest', 'capabilities' => ['completion_chat' => true]],
                ['id' => 'mistral-embed', 'capabilities' => ['completion_chat' => false]],
            ]]),
            'openrouter.ai/*' => Http::response(['data' => [
                ['id' => 'openai/gpt-4o-mini', 'architecture' => ['output_modalities' => ['text']]],
                ['id' => 'some/image-model', 'architecture' => ['output_modalities' => ['image']]],
            ]]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'mistral', 'api_key' => 'k'])
            ->assertExactJson(['data' => ['mistral-small-latest']]);

        // OpenRouter's list is public; no key is sent when none is available.
        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'openrouter'])
            ->assertExactJson(['data' => ['openai/gpt-4o-mini']]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'openrouter.ai') && !$r->hasHeader('Authorization'));
    }

    public function test_results_are_cached_per_key(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['data' => [['id' => 'llama-3.3-70b-versatile']]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'groq', 'api_key' => 'a'])->assertOk();
        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'groq', 'api_key' => 'a'])->assertOk();
        Http::assertSentCount(1);

        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'groq', 'api_key' => 'b'])->assertOk();
        Http::assertSentCount(2);
    }

    public function test_provider_errors_return_502_with_message(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/user/ai/models', ['provider' => 'openai'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not load models. AI provider returned HTTP 401: Incorrect API key provided Enter your API key for this provider first.');
    }

    public function test_custom_provider_requires_a_public_https_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'custom'])
            ->assertStatus(422)->assertJsonValidationErrors('base_url');

        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'custom', 'base_url' => 'https://169.254.169.254/latest'])
            ->assertStatus(422)->assertJsonValidationErrors('base_url');

        OpenAiCompatibleClient::$resolver = fn () => ['93.184.216.34'];
        Http::fake(['llm.example.com/*' => Http::response(['data' => [['id' => 'my-model']]])]);
        $this->actingAs($user)->postJson('/api/user/ai/models', ['provider' => 'custom', 'base_url' => 'https://llm.example.com/v1', 'api_key' => 'k'])
            ->assertOk()->assertExactJson(['data' => ['my-model']]);
    }

    public function test_unknown_provider_and_guests_are_rejected(): void
    {
        $this->postJson('/api/user/ai/models', ['provider' => 'openai'])->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/user/ai/models', ['provider' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors('provider');
    }
}
