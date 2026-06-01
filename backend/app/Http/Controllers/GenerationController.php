<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCritiqueJob;
use App\Models\Persona;
use App\Models\Project;
use App\Models\ProjectContext;
use App\Models\RequirementDraft;
use App\Services\GeminiGenerationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationController extends Controller
{
    public function __construct(private GeminiGenerationService $gemini) {}

    public function brd(Request $request, Project $project): StreamedResponse
    {
        return $this->generate($request, $project, 'brd');
    }

    public function stories(Request $request, Project $project): StreamedResponse
    {
        return $this->generate($request, $project, 'stories');
    }

    public function spec(Request $request, Project $project): StreamedResponse
    {
        return $this->generate($request, $project, 'spec');
    }

    private function generate(Request $request, Project $project, string $type): StreamedResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        $request->validate([
            'reviewer_persona_ids' => ['nullable', 'array'],
            'reviewer_persona_ids.*' => ['exists:personas,id'],
            'context_ids' => ['nullable', 'array'],
            'context_ids.*' => ['exists:project_contexts,id'],
            'brd_draft_id' => $type !== 'brd' ? ['required', 'exists:requirement_drafts,id'] : ['nullable'],
            'stories_draft_id' => $type === 'spec' ? ['required', 'exists:requirement_drafts,id'] : ['nullable'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        set_time_limit(0);

        $this->gemini->forUser($request->user(), $project);

        $leadPersona = $project->leadPersona;
        if (!$leadPersona) {
             // Fallback to general PM if not set (for legacy projects)
             $leadPersona = Persona::where('slug', 'lead_general_pm')->first() ?? Persona::where('role', 'lead')->first();
        }

        $reviewers = Persona::whereIn('id', $request->reviewer_persona_ids ?? [])->get();

        // Merge project's linked contexts with any extra contexts provided in the request
        $projectContexts = $project->projectContexts;
        $requestContexts = ProjectContext::whereIn('id', $request->context_ids ?? [])->get();
        $contexts = $projectContexts->concat($requestContexts)->unique('id');

        if ($type === 'stories') {
            $brdDraft = RequirementDraft::findOrFail($request->brd_draft_id);
            if (!in_array($brdDraft->status, ['approved', 'committed'])) {
                abort(422, 'BRD draft must be approved before generating stories.');
            }
        }

        if ($type === 'spec') {
            $brdDraft = RequirementDraft::findOrFail($request->brd_draft_id);
            $storiesDraft = RequirementDraft::findOrFail($request->stories_draft_id);
            if (!in_array($brdDraft->status, ['approved', 'committed']) || !in_array($storiesDraft->status, ['approved', 'committed'])) {
                abort(422, 'BRD and User Stories drafts must be approved before generating spec.');
            }
        }

        $draft = $project->drafts()->create([
            'type' => $type,
            'version' => $this->nextVersion($project, $type),
            'title' => $request->title,
            'description' => $request->description,
            'content' => '',
            'status' => 'drafting',
            'lead_persona_id' => $leadPersona->id,
            'reviewer_persona_ids' => $reviewers->pluck('id')->toArray(),
            'critiques' => [], // Cast handles this, but ensures it's {} in DB
        ]);

        return response()->stream(function () use ($project, $draft, $leadPersona, $reviewers, $contexts, $type, $request) {
            try {
                $accumulated = '';

                $onChunk = function (string $chunk) use (&$accumulated) {
                    $accumulated .= $chunk;
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                };

                if ($type === 'brd') {
                    $intake = $project->intake?->fields ?? [];
                    $chatHistory = $project->chatMessages()->orderBy('order')->get()->toArray();
                    $this->gemini->streamBrd($leadPersona->system_prompt, $intake, $chatHistory, $contexts->toArray(), $onChunk, $draft);
                } elseif ($type === 'stories') {
                    $brd = RequirementDraft::findOrFail($request->brd_draft_id)->content;
                    $this->gemini->streamStories($leadPersona->system_prompt, $brd, $contexts->toArray(), $onChunk, $draft);
                } elseif ($type === 'spec') {
                    $brd = RequirementDraft::findOrFail($request->brd_draft_id)->content;
                    $stories = RequirementDraft::findOrFail($request->stories_draft_id)->content;
                    $this->gemini->streamSpec($leadPersona->system_prompt, $brd, $stories, $contexts->toArray(), $onChunk, $draft);
                }

                $draft->update(['content' => $accumulated]);

                if ($reviewers->isNotEmpty()) {
                    app(\App\Services\GraphOrchestratorService::class)->transition($project, 'reviewing', [
                        'active_draft_id' => $draft->id,
                        'reviewer_ids' => $reviewers->pluck('id')->toArray()
                    ]);
                    
                    foreach ($reviewers as $reviewer) {
                        ProcessCritiqueJob::dispatch($draft, $reviewer);
                    }
                    echo 'data: ' . json_encode(['status' => 'reviewing']) . "\n\n";
                } else {
                    app(\App\Services\GraphOrchestratorService::class)->transition($project, 'approved', [
                        'active_draft_id' => $draft->id
                    ]);
                    echo 'data: ' . json_encode(['status' => 'approved']) . "\n\n";
                }

                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("{$type} generation failed: " . $e->getMessage());
                app(\App\Services\GraphOrchestratorService::class)->transition($project, 'failed', [
                    'active_draft_id' => $draft->id,
                    'error' => $e->getMessage()
                ]);
                echo 'data: ' . json_encode(['error' => 'Generation failed: ' . $e->getMessage()]) . "\n\n";
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

    public function synthesize(Request $request, Project $project, RequirementDraft $draft): StreamedResponse
    {
        if ($project->user_id !== $request->user()->id || $draft->project_id !== $project->id) {
            abort(403);
        }

        if (!in_array($draft->status, ['refining', 'failed'])) {
            abort(422, 'Draft is not ready for synthesis. Status: ' . $draft->status);
        }

        set_time_limit(0);
        $this->gemini->forUser($request->user(), $project);
        
        $leadPersona = $draft->leadPersona;

        return response()->stream(function () use ($project, $draft, $leadPersona) {
            try {
                $accumulated = '';
                $critiques = $draft->critiques()->with('persona')->get()
                    ->pluck('content', 'persona.name')
                    ->toArray();

                $this->gemini->streamSynthesis(
                    $leadPersona->system_prompt,
                    $draft->content,
                    $critiques,
                    function (string $chunk) use (&$accumulated) {
                        $accumulated .= $chunk;
                        echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    },
                    $draft->type,
                    $draft
                );

                $draft->update([
                    'content' => $accumulated
                ]);

                app(\App\Services\GraphOrchestratorService::class)->transition($project, 'approved', [
                    'active_draft_id' => $draft->id
                ]);

                echo 'data: ' . json_encode(['status' => 'approved']) . "\n\n";
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Synthesis failed: ' . $e->getMessage());
                app(\App\Services\GraphOrchestratorService::class)->transition($project, 'failed', [
                    'active_draft_id' => $draft->id,
                    'error' => $e->getMessage()
                ]);
                echo 'data: ' . json_encode(['error' => 'Synthesis failed: ' . $e->getMessage()]) . "\n\n";
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

    private function nextVersion(Project $project, string $type): int
    {
        return ($project->drafts()->where('type', $type)->max('version') ?? 0) + 1;
    }
}
