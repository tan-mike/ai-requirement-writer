<?php

namespace App\Jobs;

use App\Models\Persona;
use App\Models\RequirementDraft;
use App\Services\GeminiGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCritiqueJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 30; // Wait 30 seconds before retrying
    public $timeout = 600; // 10 minutes for complex reviews

    public function __construct(
        public RequirementDraft $draft,
        public Persona $reviewer
    ) {}

    public function handle(GeminiGenerationService $gemini): void
    {
        set_time_limit(0); // Prevent premature killing

        $gemini->forUser($this->draft->project->user);

        $critiqueContent = $gemini->generateCritique(
            $this->reviewer->system_prompt,
            $this->draft->content,
            $this->draft->type
        );

        // Save to the new table
        \App\Models\DraftCritique::updateOrCreate(
            ['requirement_draft_id' => $this->draft->id, 'persona_id' => $this->reviewer->id],
            ['content' => $critiqueContent, 'status' => 'completed']
        );

        // Refresh model to get latest state
        $this->draft->refresh();

        // If all reviewers are done, update status to 'refining'
        $totalExpected = count($this->draft->reviewer_persona_ids ?? []);
        $completedCount = $this->draft->critiques()->where('status', 'completed')->count();

        if ($completedCount === $totalExpected) {
            $this->draft->update(['status' => 'refining']);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->draft->update(['status' => 'failed']);
        \Illuminate\Support\Facades\Log::error("Critique Job Failed Permanently: " . $exception->getMessage());
    }
}
