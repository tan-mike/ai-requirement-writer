<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\AiGenerationLog;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGenerationLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_critique_generation()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        
        $service = app(GeminiGenerationService::class)->forUser($user, $project);
        
        try {
            // This might fail if no API key is set, but should still log a failure
            $service->generateCritique("You are a reviewer", "Some draft content", "brd");
        } catch (\Exception $e) {
            // Expected if no real API key
        }

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'type' => 'critique',
        ]);
    }
}
