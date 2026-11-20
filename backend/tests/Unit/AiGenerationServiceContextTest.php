<?php

namespace Tests\Unit;

use App\Models\ProjectContext;
use App\Models\User;
use App\Services\AiGenerationService;
use App\Services\MemoryGovernorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ReflectionMethod;

class AiGenerationServiceContextTest extends TestCase
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

        $service = app(AiGenerationService::class);
        
        $method = new ReflectionMethod(AiGenerationService::class, 'getCompanyContext');
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

        $service = app(AiGenerationService::class);
        $service->forUser($user, $project);
        
        $method = new ReflectionMethod(AiGenerationService::class, 'getModuleContexts');
        $method->setAccessible(true);
        $result = $method->invoke($service);

        $this->assertStringContainsString('### Module: Legacy Auth', $result);
        $this->assertStringContainsString('Use SOAP.', $result);
    }
}
