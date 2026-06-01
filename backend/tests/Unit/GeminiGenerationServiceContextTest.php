<?php

namespace Tests\Unit;

use App\Models\ProjectContext;
use App\Models\User;
use App\Services\GeminiGenerationService;
use App\Services\MemoryGovernorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ReflectionMethod;

class GeminiGenerationServiceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_global_contexts_returns_formatted_string(): void
    {
        $user = User::factory()->create();
        
        ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'Security Policy',
            'content' => 'Always use HTTPS.',
            'is_global' => true,
        ]);

        ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'UX Standard',
            'content' => 'Mobile first.',
            'is_global' => true,
        ]);

        ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'Local Context',
            'content' => 'Ignore me.',
            'is_global' => false,
        ]);

        $service = app(GeminiGenerationService::class);
        
        $method = new ReflectionMethod(GeminiGenerationService::class, 'getGlobalContexts');
        $method->setAccessible(true);
        $result = $method->invoke($service);

        $this->assertStringContainsString('### Global Mandate: Security Policy', $result);
        $this->assertStringContainsString('Always use HTTPS.', $result);
        $this->assertStringContainsString('### Global Mandate: UX Standard', $result);
        $this->assertStringContainsString('Mobile first.', $result);
        $this->assertStringNotContainsString('Local Context', $result);
        $this->assertStringNotContainsString('Ignore me.', $result);
    }
}
