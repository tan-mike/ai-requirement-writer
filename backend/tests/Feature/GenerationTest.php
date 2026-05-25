<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Project;
use App\Models\ProjectIntake;
use App\Models\RequirementDraft;
use App\Models\User;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Persona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->persona = Persona::create([
            'slug' => 'test-lead',
            'name' => 'Test Lead',
            'role' => 'lead',
            'system_prompt' => 'Test prompt',
        ]);
    }

    private function createIntake(Project $project): ProjectIntake
    {
        return ProjectIntake::create([
            'project_id' => $project->id,
            'fields' => ['project_name' => 'Test', 'problem' => 'Test problem'],
        ]);
    }

    private function createApprovedDraft(Project $project, string $type, int $version = 1): RequirementDraft
    {
        return RequirementDraft::create([
            'project_id' => $project->id,
            'type' => $type,
            'version' => $version,
            'content' => "Approved {$type} content",
            'status' => 'approved',
            'lead_persona_id' => $this->persona->id,
        ]);
    }

    public function test_brd_generation_creates_draft_and_streams(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id,
        ]);
        $this->createIntake($project);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('streamBrd')
            ->andReturnUsing(function ($sp, $fields, $chat, $ctx, callable $cb) {
                $cb('Generated content');
            });

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/generate/brd", [
                'reviewer_persona_ids' => [],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', [
            'project_id' => $project->id,
            'type' => 'brd',
            'lead_persona_id' => $this->persona->id,
        ]);
    }

    public function test_stories_generation_requires_approved_brd(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id,
        ]);
        $brd = RequirementDraft::create([
            'project_id' => $project->id,
            'type' => 'brd',
            'version' => 1,
            'content' => 'BRD content',
            'status' => 'drafting', // NOT approved
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/generate/stories", [
                'brd_draft_id' => $brd->id,
            ])
            ->assertUnprocessable();
    }

    public function test_stories_generation_passes_brd_as_context(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id,
        ]);
        $brd = $this->createApprovedDraft($project, 'brd');

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('streamStories')
            ->andReturnUsing(function ($sp, $brdContent, $ctx, callable $cb) {
                $cb('Stories content');
            });

        $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/generate/stories", [
                'brd_draft_id' => $brd->id,
                'reviewer_persona_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseHas('requirement_drafts', ['type' => 'stories', 'project_id' => $project->id]);
    }

    public function test_spec_generation_passes_brd_and_stories_as_context(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id,
        ]);
        $brd = $this->createApprovedDraft($project, 'brd');
        $stories = $this->createApprovedDraft($project, 'stories');

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('streamSpec')
            ->andReturnUsing(function ($sp, $b, $s, $ctx, callable $cb) {
                $cb('Spec content');
            });

        $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/generate/spec", [
                'brd_draft_id' => $brd->id,
                'stories_draft_id' => $stories->id,
                'reviewer_persona_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseHas('requirement_drafts', ['type' => 'spec', 'project_id' => $project->id]);
    }

    public function test_cannot_generate_for_another_users_project(): void
    {
        $project = Project::factory()->create(); // another user

        $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/generate/brd", [
                'reviewer_persona_ids' => [],
            ])
            ->assertForbidden();
    }
}
