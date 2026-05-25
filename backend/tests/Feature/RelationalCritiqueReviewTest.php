<?php

namespace Tests\Feature;

use App\Models\DraftCritique;
use App\Models\Persona;
use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationalCritiqueReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Persona $leadPersona;
    private Persona $reviewerPersona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->leadPersona = Persona::create([
            'slug' => 'lead-pm',
            'name' => 'Lead PM',
            'role' => 'lead',
            'system_prompt' => 'You are a lead PM.',
        ]);
        $this->reviewerPersona = Persona::create([
            'slug' => 'tech-reviewer',
            'name' => 'Tech Reviewer',
            'role' => 'reviewer',
            'system_prompt' => 'You are a tech reviewer.',
        ]);
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->leadPersona->id,
        ]);
    }

    public function test_requirement_draft_api_eager_loads_critiques_and_persona(): void
    {
        $draft = RequirementDraft::create([
            'project_id' => $this->project->id,
            'type' => 'brd',
            'content' => 'Draft content',
            'version' => 1,
            'status' => 'reviewing',
        ]);

        DraftCritique::create([
            'requirement_draft_id' => $draft->id,
            'persona_id' => $this->reviewerPersona->id,
            'content' => 'Critique content',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/drafts/{$draft->id}");

        $response->assertOk()
            ->assertJsonPath('data.critiques.0.content', 'Critique content')
            ->assertJsonPath('data.critiques.0.persona.name', 'Tech Reviewer');
    }

    public function test_synthesize_uses_relational_critiques(): void
    {
        $draft = RequirementDraft::create([
            'project_id' => $this->project->id,
            'type' => 'brd',
            'content' => 'Original content',
            'version' => 1,
            'status' => 'refining',
            'lead_persona_id' => $this->leadPersona->id,
        ]);

        DraftCritique::create([
            'requirement_draft_id' => $draft->id,
            'persona_id' => $this->reviewerPersona->id,
            'content' => 'Improve X and Y',
            'status' => 'completed',
        ]);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        
        // The core of Task 3 verification:
        // streamSynthesis should receive an array with persona names as keys
        $mock->shouldReceive('streamSynthesis')
            ->with(
                $this->leadPersona->system_prompt,
                'Original content',
                ['Tech Reviewer' => 'Improve X and Y'],
                \Closure::class,
                'brd'
            )
            ->andReturnUsing(function ($sp, $content, $critiques, $onChunk) {
                $onChunk('Synthesized content');
            });

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/drafts/{$draft->id}/synthesize");

        $response->assertOk();

        // Trigger the streamed response callback
        ob_start();
        $response->sendContent();
        ob_end_clean();

        $this->assertDatabaseHas('requirement_drafts', [
            'id' => $draft->id,
            'content' => 'Synthesized content',
            'status' => 'approved',
        ]);
    }
}
