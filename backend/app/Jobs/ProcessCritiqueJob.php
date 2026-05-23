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

    public $timeout = 600; // 10 minutes for complex reviews

    public function __construct(
        public RequirementDraft $draft,
        public Persona $reviewer,
        public bool $freeTier = false
    ) {}

    public function handle(GeminiGenerationService $gemini): void
    {
        set_time_limit(0); // Prevent premature killing

        $gemini->forUser($this->draft->project->user);

        $critique = $gemini->generateCritique(
            $this->reviewer->system_prompt,
            $this->draft->content
        );

        // Atomic JSON update to prevent race conditions
        $this->draft->update([
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
