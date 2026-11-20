<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\OpenAiCompatibleClient;
use App\Services\AiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonalAiCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Avoid real DNS lookups: treat every hostname as a public address.
        OpenAiCompatibleClient::$resolver = fn (string $host) => ['93.184.216.34'];
    }

    protected function tearDown(): void
    {
        OpenAiCompatibleClient::$resolver = null;
        parent::tearDown();
    }

    public function test_user_can_save_personal_ai_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/user/settings', [
                'free_tier' => true,
                'ai_api_key' => 'sk-test-key-123',
                'ai_base_url' => 'https://api.openai.com/v1',
                'ai_model' => 'gpt-4o-mini',
            ])
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertEquals('sk-test-key-123', $fresh->ai_api_key);
        $this->assertEquals('https://api.openai.com/v1', $fresh->ai_base_url);
        $this->assertEquals('gpt-4o-mini', $fresh->ai_model);
        $this->assertArrayNotHasKey('ai_api_key', $fresh->toArray());

        $this->actingAs($user)->getJson('/api/user/settings')
            ->assertOk()
            ->assertJsonPath('data.ai_base_url', 'https://api.openai.com/v1')
            ->assertJsonPath('data.ai_model', 'gpt-4o-mini')
            ->assertJsonPath('data.platform_model', 'test-model');
    }

    public function test_base_url_must_be_https(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/user/settings', ['ai_base_url' => 'http://api.openai.com/v1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_base_url');
    }

    public function test_base_url_must_not_point_to_a_private_host(): void
    {
        $user = User::factory()->create();

        foreach (['https://169.254.169.254/latest', 'https://10.0.0.5/v1', 'https://127.0.0.1/v1'] as $url) {
            $this->actingAs($user)
                ->patchJson('/api/user/settings', ['ai_base_url' => $url])
                ->assertStatus(422)
                ->assertJsonValidationErrors('ai_base_url');
        }

        OpenAiCompatibleClient::$resolver = fn (string $host) => ['192.168.1.20'];
        $this->actingAs($user)
            ->patchJson('/api/user/settings', ['ai_base_url' => 'https://internal.example.com/v1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_base_url');
    }

    public function test_preset_base_urls_skip_the_host_lookup(): void
    {
        // A resolver that would reject everything proves presets are not looked up.
        OpenAiCompatibleClient::$resolver = fn (string $host) => ['10.0.0.1'];
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/user/settings', ['ai_api_key' => 'k', 'ai_base_url' => 'https://api.groq.com/openai/v1'])
            ->assertOk();

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);
        app(AiGenerationService::class)->forUser($user->fresh())->generateCritique('sys', 'draft');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request['model'] === 'llama-3.3-70b-versatile');
    }

    public function test_service_uses_personal_key_base_url_and_model(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $user = User::factory()->create([
            'ai_api_key' => 'user-key',
            'ai_base_url' => 'https://api.openai.com/v1/',
            'ai_model' => 'gpt-4o-mini',
        ]);
        $service = app(AiGenerationService::class)->forUser($user);

        $this->assertTrue($service->usesPersonalKey());
        $this->assertSame('gpt-4o-mini', $service->getModel());

        $service->generateCritique('sys', 'draft');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer user-key')
            && $request['model'] === 'gpt-4o-mini');
    }

    public function test_personal_key_without_overrides_defaults_to_gemini(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $user = User::factory()->create(['ai_api_key' => 'user-key']);
        $service = app(AiGenerationService::class)->forUser($user);
        $service->generateCritique('sys', 'draft');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer user-key')
            && $request['model'] === 'gemma-4-26b-a4b-it');
    }

    public function test_preset_provider_without_model_uses_preset_default_model(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $user = User::factory()->create(['ai_api_key' => 'user-key', 'ai_base_url' => 'https://api.openai.com/v1/']);
        $service = app(AiGenerationService::class)->forUser($user);

        $this->assertSame('gpt-4o-mini', $service->getModel());
    }

    public function test_custom_provider_without_model_falls_back_to_platform_model(): void
    {
        $user = User::factory()->create(['ai_api_key' => 'user-key', 'ai_base_url' => 'https://llm.example.com/v1']);
        $service = app(AiGenerationService::class)->forUser($user);

        $this->assertSame('test-model', $service->getModel());
    }

    public function test_settings_lists_provider_presets_with_gemini_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user/settings')->assertOk();

        $response->assertJsonPath('data.default_provider', 'gemini');
        $providers = collect($response->json('data.providers'));
        $this->assertSame('gemini', $providers->first()['id']);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/openai', $providers->firstWhere('id', 'gemini')['base_url']);
        $this->assertTrue($providers->every(fn ($p) => isset($p['id'], $p['label'], $p['base_url'], $p['default_model'])));
    }

    public function test_overrides_are_ignored_without_a_personal_key(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $user = User::factory()->create([
            'ai_api_key' => null,
            'ai_base_url' => 'https://api.openai.com/v1',
            'ai_model' => 'gpt-4o',
        ]);
        $service = app(AiGenerationService::class)->forUser($user);

        $this->assertFalse($service->usesPersonalKey());
        $this->assertSame('test-model', $service->getModel());

        $service->generateCritique('sys', 'draft');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key-for-testing'));
    }

    public function test_user_base_url_is_rechecked_at_request_time(): void
    {
        Http::fake();

        // Saved earlier, now resolves to a private address.
        $user = User::factory()->create(['ai_api_key' => 'k', 'ai_base_url' => 'https://rebind.example.com/v1']);
        OpenAiCompatibleClient::$resolver = fn (string $host) => ['10.1.2.3'];

        $this->expectExceptionMessage('The AI base URL must point to a public host.');
        try {
            app(AiGenerationService::class)->forUser($user)->generateCritique('sys', 'draft');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_missing_platform_key_throws_runtime_exception(): void
    {
        config(['ai.api_key' => null]);
        $user = User::factory()->create(['ai_api_key' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No AI API key configured');
        app(AiGenerationService::class)->forUser($user);
    }

    public function test_effective_free_tier_logic_user_key(): void
    {
        $user = User::factory()->create([
            'ai_api_key' => 'user-key',
            'free_tier' => true,
        ]);
        config(['ai.platform_free_tier' => false]);

        $service = app(AiGenerationService::class);
        $service->forUser($user);

        $reflector = new \ReflectionClass($service);
        $freeTier = $reflector->getProperty('effectiveFreeTier');
        $freeTier->setAccessible(true);
        $this->assertTrue($freeTier->getValue($service));
    }

    public function test_effective_free_tier_logic_platform_key(): void
    {
        $user = User::factory()->create([
            'ai_api_key' => null,
            'free_tier' => true, // should be ignored
        ]);
        config(['ai.platform_free_tier' => false]);

        $service = app(AiGenerationService::class);
        $service->forUser($user);

        $reflector = new \ReflectionClass($service);
        $freeTier = $reflector->getProperty('effectiveFreeTier');
        $freeTier->setAccessible(true);
        $this->assertFalse($freeTier->getValue($service));
    }
}
