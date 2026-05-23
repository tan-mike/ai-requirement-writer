<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeminiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_personal_api_key(): void
    {
        $user = User::factory()->create();
        $key = 'AIzaSy-test-key-123';

        $this->actingAs($user)
            ->patchJson('/api/user/settings', [
                'free_tier' => true,
                'gemini_api_key' => $key,
            ])
            ->assertOk();

        $this->assertEquals($key, $user->fresh()->gemini_api_key);
        // Assert it is hidden from default serialization
        $this->assertArrayNotHasKey('gemini_api_key', $user->fresh()->toArray());
    }

    public function test_service_uses_personal_key_when_provided(): void
    {
        $user = User::factory()->create(['gemini_api_key' => 'user-key']);
        $service = new GeminiGenerationService();
        
        $service->forUser($user);
        
        $reflector = new \ReflectionClass($service);
        $customClient = $reflector->getProperty('customClient');
        $customClient->setAccessible(true);
        
        $this->assertNotNull($customClient->getValue($service));
    }

    public function test_service_falls_back_to_platform_key_when_missing(): void
    {
        $user = User::factory()->create(['gemini_api_key' => null]);
        $service = new GeminiGenerationService();
        
        $service->forUser($user);
        
        $reflector = new \ReflectionClass($service);
        $customClient = $reflector->getProperty('customClient');
        $customClient->setAccessible(true);
        
        $this->assertNull($customClient->getValue($service));
    }

    public function test_effective_free_tier_logic_user_key(): void
    {
        $user = User::factory()->create([
            'gemini_api_key' => 'user-key',
            'free_tier' => true,
        ]);
        
        config(['gemini.platform_free_tier' => false]);
        
        $service = new GeminiGenerationService();
        $service->forUser($user);
        
        $reflector = new \ReflectionClass($service);
        $freeTier = $reflector->getProperty('effectiveFreeTier');
        $freeTier->setAccessible(true);
        
        $this->assertTrue($freeTier->getValue($service));
    }

    public function test_effective_free_tier_logic_platform_key(): void
    {
        $user = User::factory()->create([
            'gemini_api_key' => null,
            'free_tier' => true, // should be ignored
        ]);
        
        config(['gemini.platform_free_tier' => false]);
        
        $service = new GeminiGenerationService();
        $service->forUser($user);
        
        $reflector = new \ReflectionClass($service);
        $freeTier = $reflector->getProperty('effectiveFreeTier');
        $freeTier->setAccessible(true);
        
        $this->assertFalse($freeTier->getValue($service));
    }
}
