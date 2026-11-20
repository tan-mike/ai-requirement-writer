<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use App\Services\AiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGenerationLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_successful_critique_generation()
    {
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Test critique response']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $service = app(AiGenerationService::class)->forUser($user)->forFeature($feature);
        $result = $service->generateCritique("You are a reviewer", "Some draft content", "brd");

        $this->assertSame('Test critique response', $result);
        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'feature_id' => $feature->id,
            'type' => 'critique',
            'model' => 'test-model',
            'status' => 'success',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ai.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key-for-testing')
                && $request['model'] === 'test-model'
                && $request['messages'][0] === ['role' => 'system', 'content' => 'You are a reviewer']
                && $request['messages'][1]['role'] === 'user'
                && !isset($request['stream']);
        });
    }

    public function test_it_logs_failed_critique_generation()
    {
        Http::fake([
            'ai.test/*' => Http::response(['error' => ['message' => 'Quota exceeded', 'code' => 429]], 429),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $service = app(AiGenerationService::class)->forUser($user)->forFeature($feature);

        try {
            $service->generateCritique("You are a reviewer", "Some draft content", "brd");
            $this->fail('Expected an exception');
        } catch (\Exception $e) {
            $this->assertSame('AI provider returned HTTP 429: Quota exceeded', $e->getMessage());
        }

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'feature_id' => $feature->id,
            'type' => 'critique',
            'status' => 'failure',
            'error_message' => 'AI provider returned HTTP 429: Quota exceeded',
        ]);
    }

    public function test_it_streams_a_document_and_logs_usage()
    {
        $body = "\n" . str_repeat('Requirement line. ', 40);
        Http::fake([
            'ai.test/*' => Http::response($this->sse([
                ['choices' => [['delta' => ['content' => '# BRD']]]],
                ['choices' => [['delta' => ['content' => $body]]]],
                ['choices' => [], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12]],
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $chunks = [];
        app(AiGenerationService::class)->forUser($user)->forFeature($feature)
            ->streamBrd('system', ['Goal' => 'x'], [], [], function (string $chunk) use (&$chunks) {
                $chunks[] = $chunk;
            });

        $this->assertSame('# BRD' . $body, implode('', $chunks));
        $this->assertDatabaseHas('ai_generation_logs', [
            'type' => 'brd',
            'status' => 'success',
            'input_tokens' => 5,
            'output_tokens' => 7,
            'total_tokens' => 12,
        ]);

        Http::assertSent(fn (Request $request) => $request['stream'] === true
            && $request['stream_options'] === ['include_usage' => true]);
    }

    public function test_discovery_chat_sends_history_as_openai_messages()
    {
        Http::fake([
            'ai.test/*' => Http::response($this->sse([
                ['choices' => [['delta' => ['content' => 'Why?']]]],
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $output = '';
        app(AiGenerationService::class)->forUser($user)->forFeature($feature)->streamDiscoveryChat(
            'You are the challenger',
            'Arch',
            [
                ['role' => 'user', 'content' => 'I want X'],
                ['role' => 'assistant', 'content' => 'Tell me more'],
                ['role' => 'user', 'content' => 'For Y'],
            ],
            function (string $chunk) use (&$output) {
                $output .= $chunk;
            }
        );

        $this->assertSame('Why?', $output);
        Http::assertSent(function (Request $request) {
            $messages = $request['messages'];
            return count($messages) === 4
                && $messages[0]['role'] === 'system'
                && str_starts_with($messages[0]['content'], 'You are the challenger')
                && array_slice($messages, 1) === [
                    ['role' => 'user', 'content' => 'I want X'],
                    ['role' => 'assistant', 'content' => 'Tell me more'],
                    ['role' => 'user', 'content' => 'For Y'],
                ];
        });
    }

    public function test_architecture_summary_uses_platform_credentials_without_for_user()
    {
        Http::fake([
            'ai.test/*' => Http::response([
                'choices' => [['message' => ['content' => '# Summary']]],
            ]),
        ]);

        $summary = app(AiGenerationService::class)->generateArchitectureSummary('ctx');

        $this->assertSame('# Summary', $summary);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key-for-testing'));
    }

    private function sse(array $events): string
    {
        return collect($events)->map(fn ($e) => 'data: ' . json_encode($e) . "\n\n")->implode('') . "data: [DONE]\n\n";
    }
}
