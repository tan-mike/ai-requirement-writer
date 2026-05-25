<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\AiGenerationLog;
use App\Services\GeminiGenerationService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGenerationLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_successful_critique_generation()
    {
        Gemini::fake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Test critique response'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 20,
                    'totalTokenCount' => 30,
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        
        $service = app(GeminiGenerationService::class)->forUser($user, $project);
        $service->generateCritique("You are a reviewer", "Some draft content", "brd");

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'type' => 'critique',
            'status' => 'success',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
        ]);
    }

    public function test_it_logs_failed_critique_generation()
    {
        Gemini::fake([
            new \Exception('Gemini API Error'),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        
        $service = app(GeminiGenerationService::class)->forUser($user, $project);
        
        try {
            $service->generateCritique("You are a reviewer", "Some draft content", "brd");
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'type' => 'critique',
            'status' => 'failure',
            'error_message' => 'Gemini API Error',
        ]);
    }
}
