<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RequirementDraft;
use App\Services\GeminiGenerationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationController extends Controller
{
    public function __construct(private GeminiGenerationService $gemini) {}

    public function brd(Request $request, Project $project): StreamedResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        set_time_limit(120);

        $intake = $project->intake;
        $chatHistory = $project->chatMessages()->orderBy('order')->get();

        if (! $intake && $chatHistory->isEmpty()) {
            abort(422, 'Project intake or chat history is missing.');
        }

        $draft = $project->drafts()->create([
            'type' => 'brd',
            'version' => $this->nextVersion($project, 'brd'),
            'content' => '',
        ]);

        return response()->stream(function () use ($intake, $chatHistory, $draft) {
            try {
                $accumulated = '';
                $context = $intake ? $intake->fields : [];
                
                // If we have chat history, we should prioritize it or combine it.
                // For simplicity, we'll pass both to a modified streamBrd method.
                $this->gemini->streamBrd($context, $chatHistory->toArray(), function (string $chunk) use (&$accumulated) {
                    $accumulated .= $chunk;
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                });
                $draft->update(['content' => $accumulated]);
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('BRD generation failed: ' . $e->getMessage());
                echo 'data: ' . json_encode(['error' => 'Generation failed: ' . $e->getMessage()]) . "\n\n";
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

    public function stories(Request $request, Project $project): StreamedResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        set_time_limit(120);

        $request->validate([
            'brd_draft_id' => ['required', 'integer'],
        ]);

        $brdDraft = $project->drafts()->where('type', 'brd')->findOrFail($request->brd_draft_id);

        if ($brdDraft->status !== 'approved') {
            abort(422, 'BRD draft must be approved before generating stories.');
        }

        if (! $brdDraft->content) {
            return response()->json(['message' => 'BRD draft has no content.'], 422);
        }

        $draft = $project->drafts()->create([
            'type' => 'stories',
            'version' => $this->nextVersion($project, 'stories'),
            'content' => '',
        ]);

        return response()->stream(function () use ($brdDraft, $draft) {
            try {
                $accumulated = '';
                $this->gemini->streamStories($brdDraft->content, function (string $chunk) use (&$accumulated) {
                    $accumulated .= $chunk;
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                });
                $draft->update(['content' => $accumulated]);
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Stories generation failed: ' . $e->getMessage());
                echo 'data: ' . json_encode(['error' => 'Generation failed: ' . $e->getMessage()]) . "\n\n";
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

    public function spec(Request $request, Project $project): StreamedResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        set_time_limit(120);

        $request->validate([
            'brd_draft_id' => ['required', 'integer'],
            'stories_draft_id' => ['required', 'integer'],
        ]);

        $brdDraft = $project->drafts()->where('type', 'brd')->findOrFail($request->brd_draft_id);
        $storiesDraft = $project->drafts()->where('type', 'stories')->findOrFail($request->stories_draft_id);

        if ($brdDraft->status !== 'approved') {
            abort(422, 'BRD draft must be approved before generating spec.');
        }

        if ($storiesDraft->status !== 'approved') {
            abort(422, 'Stories draft must be approved before generating spec.');
        }

        if (! $brdDraft->content || ! $storiesDraft->content) {
            return response()->json(['message' => 'One or more drafts have no content.'], 422);
        }

        $draft = $project->drafts()->create([
            'type' => 'spec',
            'version' => $this->nextVersion($project, 'spec'),
            'content' => '',
        ]);

        return response()->stream(function () use ($brdDraft, $storiesDraft, $draft) {
            try {
                $accumulated = '';
                $this->gemini->streamSpec($brdDraft->content, $storiesDraft->content, function (string $chunk) use (&$accumulated) {
                    $accumulated .= $chunk;
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                });
                $draft->update(['content' => $accumulated]);
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Spec generation failed: ' . $e->getMessage());
                echo 'data: ' . json_encode(['error' => 'Generation failed: ' . $e->getMessage()]) . "\n\n";
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

    private function nextVersion(Project $project, string $type): int
    {
        return ($project->drafts()->where('type', $type)->max('version') ?? 0) + 1;
    }
}
