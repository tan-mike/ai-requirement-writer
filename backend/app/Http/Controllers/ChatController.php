<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\ChatMessage;
use App\Services\GeminiGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private GeminiGenerationService $gemini) {}

    public function index(Feature $feature): JsonResponse
    {
        return response()->json(['data' => $feature->chatMessages()->orderBy('created_at')->get()]);
    }

    public function clear(Feature $feature): JsonResponse
    {
        $feature->chatMessages()->delete();
        return response()->json(['message' => 'Chat history cleared']);
    }

    public function chat(Request $request, Feature $feature): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $user = $request->user();
        $project = $feature->project;

        // Save user message
        $feature->chatMessages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        set_time_limit(0);

        try {
            $this->gemini->forUser($user)->forFeature($feature);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        $leadPersona = $feature->leadPersona;
        if (!$leadPersona) {
            $leadPersona = \App\Models\Persona::where('role', 'lead')->first();
        }

        return response()->stream(function () use ($feature, $leadPersona, $user) {
            try {
                $accumulated = '';
                $history = $feature->chatMessages()->orderBy('created_at')->get()->toArray();
                
                $this->gemini->streamDiscoveryChat(
                    $leadPersona->system_prompt,
                    $feature->project->architecture_summary ?? '',
                    $history,
                    function (string $chunk) use (&$accumulated) {
                        $accumulated .= $chunk;
                        echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    }
                );

                // Save assistant message
                $feature->chatMessages()->create([
                    'user_id' => null, // system/assistant
                    'role' => 'assistant',
                    'content' => $accumulated,
                ]);

                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode(['error' => 'Chat failed: ' . $e->getMessage()]) . "\n\n";
                echo "data: [DONE]\n\n";
            }
            if (ob_get_level() > 0) ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
