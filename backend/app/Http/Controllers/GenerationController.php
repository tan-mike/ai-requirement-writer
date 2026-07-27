<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCritiqueJob;
use App\Models\Persona;
use App\Models\Feature;
use App\Models\ProjectContext;
use App\Models\RequirementDraft;
use App\Services\GeminiGenerationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationController extends Controller
{
    public function __construct(private GeminiGenerationService $gemini) {}

    public function brd(Request $request, Feature $feature): StreamedResponse
    {
        return $this->generate($request, $feature, 'brd');
    }

    public function stories(Request $request, Feature $feature): StreamedResponse
    {
        return $this->generate($request, $feature, 'stories');
    }

    public function spec(Request $request, Feature $feature): StreamedResponse
    {
        return $this->generate($request, $feature, 'spec');
    }

    private function generate(Request $request, Feature $feature, string $type): StreamedResponse
    {
        $project = $feature->project;

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

        try {
            $this->gemini->forUser($request->user())->forFeature($feature);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        set_time_limit(0);

        $leadPersona = $feature->leadPersona;
        if (!$leadPersona) {
             $leadPersona = Persona::where('slug', 'lead_general_ba')->first() ?? Persona::where('role', 'lead')->first();
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

        $draft = $feature->drafts()->create([
            'user_id' => $request->user()->id,
            'type' => $type,
            'version' => $this->nextVersion($feature, $type, $request->user()->id),
            'title' => $request->title,
            'description' => $request->description,
            'content' => '',
            'status' => 'drafting',
            'lead_persona_id' => $leadPersona->id,
            'reviewer_persona_ids' => $reviewers->pluck('id')->toArray(),
            'context_ids' => $contexts->pluck('id')->values()->all(),
            'model' => $this->gemini->getModel(),
        ]);

        return response()->stream(function () use ($feature, $draft, $leadPersona, $reviewers, $contexts, $type, $request) {
            try {
                $accumulated = '';

                $onChunk = function (string $chunk) use (&$accumulated) {
                    $accumulated .= $chunk;
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                };

                if ($type === 'brd') {
                    $intake = $feature->intake?->fields ?? [];
                    $chatHistory = $feature->chatMessages()->orderBy('created_at')->get()->toArray();
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
                    app(\App\Services\GraphOrchestratorService::class)->transition($feature, 'reviewing', [
                        'active_draft_id' => $draft->id,
                        'reviewer_ids' => $reviewers->pluck('id')->toArray()
                    ]);
                    
                    foreach ($reviewers as $reviewer) {
                        ProcessCritiqueJob::dispatch($draft, $reviewer);
                    }
                    echo 'data: ' . json_encode(['status' => 'reviewing']) . "\n\n";
                } else {
                    // No reviewers selected: leave draft in 'drafting' state so the user
                    // can manually review, edit, and approve via the DraftEditor UI.
                    echo 'data: ' . json_encode(['status' => 'drafting']) . "\n\n";
                }

                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("{$type} generation failed: " . $e->getMessage());
                app(\App\Services\GraphOrchestratorService::class)->transition($feature, 'failed', [
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

    public function synthesize(Request $request, Feature $feature, RequirementDraft $draft): StreamedResponse
    {
        if (!in_array($draft->status, ['refining', 'failed'])) {
            abort(422, 'Draft is not ready for synthesis. Status: ' . $draft->status);
        }

        set_time_limit(0);

        try {
            $this->gemini->forUser($request->user())->forFeature($feature);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
        
        $leadPersona = $draft->leadPersona;

        return response()->stream(function () use ($feature, $draft, $leadPersona) {
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
                    'content' => $accumulated,
                    'status' => 'drafting',
                ]);

                // Leave draft in 'drafting' so user can review synthesized output,
                // make final edits, and manually approve when ready.
                echo 'data: ' . json_encode(['status' => 'drafting']) . "\n\n";
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Synthesis failed: ' . $e->getMessage());
                app(\App\Services\GraphOrchestratorService::class)->transition($feature, 'failed', [
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

    private function nextVersion(Feature $feature, string $type, int $userId): int
    {
        return ($feature->drafts()->where('type', $type)->where('user_id', $userId)->max('version') ?? 0) + 1;
    }
}
