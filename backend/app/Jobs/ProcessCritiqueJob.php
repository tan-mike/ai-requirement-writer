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

        $critique = $gemini->generateCritique(
            $this->reviewer->system_prompt,
            $this->draft->content,
            $this->draft->type
        );

        // Ensure critiques is initialized as an object if NULL (Atomic check)
        RequirementDraft::where('id', $this->draft->id)
            ->whereNull('critiques')
            ->update(['critiques' => '{}']);

        // Use Query Builder for atomic JSON update to prevent race conditions
        RequirementDraft::where('id', $this->draft->id)->update([
            "critiques->{$this->reviewer->name}" => $critique
        ]);

        // Refresh model to get latest critiques from other workers
        $this->draft->refresh();
        $critiques = $this->draft->critiques ?? [];

        // If all reviewers are done, update status to 'refining'
        $totalReviewers = count($this->draft->reviewer_persona_ids ?? []);
        if (count($critiques) === $totalReviewers) {
            $this->draft->update(['status' => 'refining']);
        }
    }
}
