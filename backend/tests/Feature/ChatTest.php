<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_chat_messages(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->chatMessages()->create(['role' => 'user', 'content' => 'Hello', 'order' => 1]);

        $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Hello');
    }

    public function test_chat_streams_and_saves_messages(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('streamDiscoveryChat')
            ->andReturnUsing(function ($summary, $history, callable $cb) {
                $cb('AI response');
            });

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/chat", [
                'message' => 'User message',
            ]);

        $response->assertOk();
        
        // Consume the stream to trigger the save
        ob_start();
        $response->baseResponse->sendContent();
        ob_get_clean();
        
        $this->assertDatabaseHas('chat_messages', [
            'project_id' => $project->id,
            'role' => 'user',
            'content' => 'User message',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'project_id' => $project->id,
            'role' => 'assistant',
            'content' => 'AI response',
        ]);
    }

    public function test_chat_uses_architecture_summary_if_present(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'architecture_summary' => 'Existing Architecture Context',
        ]);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('streamDiscoveryChat')
            ->withArgs(function ($summary, $history) {
                return $summary === 'Existing Architecture Context';
            }, \Mockery::any(), \Mockery::any())
            ->andReturn(null);

        $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/chat", [
                'message' => 'Analyze this',
            ]);
    }

    public function test_brd_generation_uses_chat_history_context(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $project->chatMessages()->create(['role' => 'user', 'content' => 'Discovery info', 'order' => 1]);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('streamBrd')
            ->withArgs(function ($fields, $chatHistory) {
                return count($chatHistory) === 1 && $chatHistory[0]['content'] === 'Discovery info';
            }, \Mockery::any())
            ->andReturn(null);

        $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/generate/brd")
            ->assertOk();
    }
}
