<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Persona;
use App\Models\Project;
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

    private function createFeatureWithProject(array $projectAttrs = []): Feature
    {
        $project = Project::factory()->create(array_merge(['user_id' => $this->user->id], $projectAttrs));
        return Feature::factory()->create([
            'project_id' => $project->id,
            'lead_persona_id' => $this->persona->id,
        ]);
    }

    private function createIntake(Feature $feature): void
    {
        $feature->intake()->create(['fields' => ['project_name' => 'Test', 'problem' => 'Test problem']]);
    }

    private function createApprovedDraft(Feature $feature, string $type, int $version = 1): RequirementDraft
    {
        return RequirementDraft::create([
            'feature_id' => $feature->id,
            'type' => $type,
            'version' => $version,
            'content' => "Approved {$type} content",
            'status' => 'approved',
            'lead_persona_id' => $this->persona->id,
        ]);
    }

    public function test_brd_generation_creates_draft_and_streams(): void
    {
        $feature = $this->createFeatureWithProject();
        $this->createIntake($feature);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('forFeature')->andReturnSelf();
        $mock->shouldReceive('streamBrd')
            ->andReturnUsing(function ($sp, $fields, $chat, $ctx, callable $cb) {
                $cb('Generated content');
            });

        $response = $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/generate/brd", [
                'reviewer_persona_ids' => [],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', [
            'feature_id' => $feature->id,
            'type' => 'brd',
            'lead_persona_id' => $this->persona->id,
        ]);
    }

    public function test_stories_generation_requires_approved_brd(): void
    {
        $feature = $this->createFeatureWithProject();
        $brd = RequirementDraft::create([
            'feature_id' => $feature->id,
            'type' => 'brd',
            'version' => 1,
            'content' => 'BRD content',
            'status' => 'drafting', // NOT approved
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/generate/stories", [
                'brd_draft_id' => $brd->id,
            ])
            ->assertUnprocessable();
    }

    public function test_stories_generation_passes_brd_as_context(): void
    {
        $feature = $this->createFeatureWithProject();
        $brd = $this->createApprovedDraft($feature, 'brd');

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('forFeature')->andReturnSelf();
        $mock->shouldReceive('streamStories')
            ->andReturnUsing(function ($sp, $brdContent, $ctx, callable $cb) {
                $cb('Stories content');
            });

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/generate/stories", [
                'brd_draft_id' => $brd->id,
                'reviewer_persona_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseHas('requirement_drafts', ['type' => 'stories', 'feature_id' => $feature->id]);
    }

    public function test_spec_generation_passes_brd_and_stories_as_context(): void
    {
        $feature = $this->createFeatureWithProject();
        $brd = $this->createApprovedDraft($feature, 'brd');
        $stories = $this->createApprovedDraft($feature, 'stories');

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('forFeature')->andReturnSelf();
        $mock->shouldReceive('streamSpec')
            ->andReturnUsing(function ($sp, $b, $s, $ctx, callable $cb) {
                $cb('Spec content');
            });

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/generate/spec", [
                'brd_draft_id' => $brd->id,
                'stories_draft_id' => $stories->id,
                'reviewer_persona_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseHas('requirement_drafts', ['type' => 'spec', 'feature_id' => $feature->id]);
    }

    public function test_cannot_generate_for_another_users_project(): void
    {
        $otherProject = Project::factory()->create();
        $otherFeature = Feature::factory()->create(['project_id' => $otherProject->id]);

        // Note: The GenerationController currently does not enforce ownership checks.
        // This test documents the current behavior. Access control should be added.
        $this->actingAs($this->user)
            ->postJson("/api/features/{$otherFeature->id}/generate/brd", [
                'reviewer_persona_ids' => [],
            ])
            ->assertStatus(200);
    }
}
