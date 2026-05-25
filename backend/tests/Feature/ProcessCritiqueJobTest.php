<?php

namespace Tests\Feature;

use App\Jobs\ProcessCritiqueJob;
use App\Models\DraftCritique;
use App\Models\Persona;
use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessCritiqueJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_critique_to_draft_critiques_table(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $draft = RequirementDraft::create([
            'project_id' => $project->id,
            'type' => 'brd',
            'content' => 'Test content',
            'version' => 1,
            'status' => 'drafting',
            'reviewer_persona_ids' => [1],
        ]);

        $reviewer = Persona::create([
            'id' => 1,
            'slug' => 'reviewer',
            'name' => 'Reviewer',
            'role' => 'reviewer',
            'system_prompt' => 'Review it.',
        ]);

        $mockGemini = $this->mock(GeminiGenerationService::class);
        $mockGemini->shouldReceive('forUser')->andReturnSelf();
        $mockGemini->shouldReceive('generateCritique')
            ->once()
            ->andReturn('Test critique content');

        $job = new ProcessCritiqueJob($draft, $reviewer);
        $job->handle($mockGemini);

        // This should fail currently because it saves to 'critiques' column
        $this->assertDatabaseHas('draft_critiques', [
            'requirement_draft_id' => $draft->id,
            'persona_id' => $reviewer->id,
            'content' => 'Test critique content',
            'status' => 'completed',
        ]);
    }

    public function test_it_updates_draft_status_to_refining_when_all_critiques_completed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        
        $reviewer1 = Persona::create([
            'slug' => 'reviewer-1',
            'name' => 'Reviewer 1',
            'role' => 'reviewer',
            'system_prompt' => 'Review 1.',
        ]);
        
        $reviewer2 = Persona::create([
            'slug' => 'reviewer-2',
            'name' => 'Reviewer 2',
            'role' => 'reviewer',
            'system_prompt' => 'Review 2.',
        ]);

        $draft = RequirementDraft::create([
            'project_id' => $project->id,
            'type' => 'brd',
            'content' => 'Test content',
            'version' => 1,
            'status' => 'drafting',
            'reviewer_persona_ids' => [$reviewer1->id, $reviewer2->id],
        ]);

        // Pre-fill first critique in the new table
        DraftCritique::create([
            'requirement_draft_id' => $draft->id,
            'persona_id' => $reviewer1->id,
            'content' => 'First critique',
            'status' => 'completed',
        ]);

        $mockGemini = $this->mock(GeminiGenerationService::class);
        $mockGemini->shouldReceive('forUser')->andReturnSelf();
        $mockGemini->shouldReceive('generateCritique')
            ->andReturn('Second critique content');

        $job = new ProcessCritiqueJob($draft, $reviewer2);
        $job->handle($mockGemini);

        $this->assertEquals('refining', $draft->fresh()->status);
    }
}
