<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ChatMessage;
use App\Services\GeminiGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private GeminiGenerationService $gemini) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        $messages = $project->chatMessages()->orderBy('order')->get();

        return response()->json(['data' => $messages]);
    }

    public function chat(Request $request, Project $project): StreamedResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        $request->validate([
            'message' => ['required', 'string'],
        ]);

        // Save user message
        $project->chatMessages()->create([
            'role' => 'user',
            'content' => $request->message,
            'order' => $project->chatMessages()->count() + 1,
        ]);

        $history = $project->chatMessages()->orderBy('order')->get()->toArray();
        $architectureSummary = $project->architecture_summary ?? 'No existing architecture information available.';

        return response()->stream(function () use ($project, $architectureSummary, $history) {
            try {
                $accumulated = '';
                $this->gemini->streamDiscoveryChat($architectureSummary, $history, function (string $chunk) use (&$accumulated) {
                    $accumulated .= $chunk;
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                });

                // Save assistant message
                $project->chatMessages()->create([
                    'role' => 'assistant',
                    'content' => $accumulated,
                    'order' => $project->chatMessages()->count() + 1,
                ]);

                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Chat failed: ' . $e->getMessage());
                echo 'data: ' . json_encode(['error' => 'Chat failed: ' . $e->getMessage()]) . "\n\n";
                echo "data: [DONE]\n\n";
            }
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
