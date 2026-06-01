<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createDraft(Project $project, array $attrs = []): RequirementDraft
    {
        return RequirementDraft::create(array_merge([
            'project_id' => $project->id,
            'type' => 'brd',
            'content' => 'Some content',
            'version' => 1,
            'status' => 'draft',
        ], $attrs));
    }

    public function test_user_can_list_drafts(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->createDraft($project, ['type' => 'brd']);
        $this->createDraft($project, ['type' => 'stories', 'version' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}/drafts");

        $response->assertOk()->assertJsonStructure(['data' => ['brd', 'stories']]);
    }

    public function test_user_can_update_draft_content(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $draft = $this->createDraft($project);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/projects/{$project->id}/drafts/{$draft->id}", [
                'content' => 'Updated content',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', ['id' => $draft->id, 'content' => 'Updated content']);
    }

    public function test_user_can_approve_draft(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $draft = $this->createDraft($project);

        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$project->id}/drafts/{$draft->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', ['id' => $draft->id, 'status' => 'committed']);
    }

    public function test_user_cannot_access_another_users_drafts(): void
    {
        $otherProject = Project::factory()->create();

        $this->actingAs($this->user)
            ->getJson("/api/projects/{$otherProject->id}/drafts")
            ->assertForbidden();
    }

    public function test_user_cannot_update_draft_from_another_project(): void
    {
        $myProject = Project::factory()->create(['user_id' => $this->user->id]);
        $otherProject = Project::factory()->create();
        $otherDraft = $this->createDraft($otherProject);

        $this->actingAs($this->user)
            ->patchJson("/api/projects/{$myProject->id}/drafts/{$otherDraft->id}", [
                'content' => 'Hacked',
            ])
            ->assertForbidden();
    }

    public function test_draft_versioning_increments(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->createDraft($project, ['type' => 'brd', 'version' => 1]);
        $this->createDraft($project, ['type' => 'brd', 'version' => 2]);

        $this->assertDatabaseCount('requirement_drafts', 2);
    }
}
