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
        ]);

        set_time_limit(0);

        $this->gemini->forUser($request->user());

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
        
        $freeTier = $request->user()->free_tier;

        if ($type === 'stories') {
            $brdDraft = RequirementDraft::findOrFail($request->brd_draft_id);
            if ($brdDraft->status !== 'approved') {
                abort(422, 'BRD draft must be approved before generating stories.');
            }
        }

        if ($type === 'spec') {
            $brdDraft = RequirementDraft::findOrFail($request->brd_draft_id);
            $storiesDraft = RequirementDraft::findOrFail($request->stories_draft_id);
            if ($brdDraft->status !== 'approved' || $storiesDraft->status !== 'approved') {
                abort(422, 'BRD and User Stories drafts must be approved before generating spec.');
            }
        }

        $draft = $project->drafts()->create([
            'type' => $type,
            'version' => $this->nextVersion($project, $type),
            'content' => '',
            'status' => 'drafting',
            'lead_persona_id' => $leadPersona->id,
            'reviewer_persona_ids' => $reviewers->pluck('id')->toArray(),
        ]);

        return response()->stream(function () use ($project, $draft, $leadPersona, $reviewers, $contexts, $freeTier, $type, $request) {
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
                    $this->gemini->streamBrd($leadPersona->system_prompt, $intake, $chatHistory, $contexts->toArray(), $onChunk, $freeTier);
                } elseif ($type === 'stories') {
                    $brd = RequirementDraft::findOrFail($request->brd_draft_id)->content;
                    $this->gemini->streamStories($leadPersona->system_prompt, $brd, $contexts->toArray(), $onChunk, $freeTier);
                } elseif ($type === 'spec') {
                    $brd = RequirementDraft::findOrFail($request->brd_draft_id)->content;
                    $stories = RequirementDraft::findOrFail($request->stories_draft_id)->content;
                    $this->gemini->streamSpec($leadPersona->system_prompt, $brd, $stories, $contexts->toArray(), $onChunk, $freeTier);
                }

                $draft->update(['content' => $accumulated]);

                if ($reviewers->isNotEmpty()) {
                    $draft->update(['status' => 'reviewing']);
                    foreach ($reviewers as $reviewer) {
                        ProcessCritiqueJob::dispatch($draft, $reviewer, $freeTier);
                    }
                    echo 'data: ' . json_encode(['status' => 'reviewing']) . "\n\n";
                } else {
                    $draft->update(['status' => 'approved']); // Auto-approve if no reviewers? Or stay 'draft'
                    echo 'data: ' . json_encode(['status' => 'approved']) . "\n\n";
                }

                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("{$type} generation failed: " . $e->getMessage());
                $draft->update(['status' => 'failed']);
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

        if ($draft->status !== 'refining') {
            abort(422, 'Draft is not ready for synthesis. Status: ' . $draft->status);
        }

        set_time_limit(0);
        $this->gemini->forUser($request->user());
        
        $leadPersona = $draft->leadPersona;

        return response()->stream(function () use ($draft, $leadPersona) {
            try {
                $accumulated = '';
                $this->gemini->streamSynthesis(
                    $leadPersona->system_prompt,
                    $draft->content,
                    $draft->critiques ?? [],
                    function (string $chunk) use (&$accumulated) {
                        $accumulated .= $chunk;
                        echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    },
                    $freeTier
                );

                $draft->update([
                    'content' => $accumulated,
                    'status' => 'approved'
                ]);
                echo 'data: ' . json_encode(['status' => 'approved']) . "\n\n";
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Synthesis failed: ' . $e->getMessage());
                $draft->update(['status' => 'failed']);
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
