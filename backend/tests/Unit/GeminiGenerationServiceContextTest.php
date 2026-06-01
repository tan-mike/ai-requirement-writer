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

    public function test_get_company_context_returns_formatted_string(): void
    {
        $user = User::factory()->create();
        
        ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'Security Policy',
            'content' => 'Always use HTTPS.',
            'is_global' => true,
        ]);

        $service = app(GeminiGenerationService::class);
        
        $method = new ReflectionMethod(GeminiGenerationService::class, 'getCompanyContext');
        $method->setAccessible(true);
        $result = $method->invoke($service);

        $this->assertStringContainsString('### Company Context: Security Policy', $result);
        $this->assertStringContainsString('Always use HTTPS.', $result);
    }

    public function test_get_module_contexts_returns_formatted_string(): void
    {
        $user = User::factory()->create();
        $project = \App\Models\Project::factory()->create(['user_id' => $user->id]);
        
        $context = ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'Legacy Auth',
            'content' => 'Use SOAP.',
            'is_global' => false,
        ]);

        $project->projectContexts()->attach($context->id);

        $service = app(GeminiGenerationService::class);
        $service->forUser($user, $project);
        
        $method = new ReflectionMethod(GeminiGenerationService::class, 'getModuleContexts');
        $method->setAccessible(true);
        $result = $method->invoke($service);

        $this->assertStringContainsString('### Module: Legacy Auth', $result);
        $this->assertStringContainsString('Use SOAP.', $result);
    }
}
