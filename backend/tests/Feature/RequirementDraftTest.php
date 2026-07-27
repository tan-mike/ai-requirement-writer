<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->feature = Feature::factory()->create(['project_id' => $project->id]);
    }

    private function createDraft(array $attrs = []): RequirementDraft
    {
        return RequirementDraft::create(array_merge([
            'feature_id' => $this->feature->id,
            'type' => 'brd',
            'content' => 'Some content',
            'version' => 1,
            'status' => 'draft',
        ], $attrs));
    }

    public function test_user_can_list_drafts(): void
    {
        $this->createDraft(['type' => 'brd']);
        $this->createDraft(['type' => 'stories', 'version' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/features/{$this->feature->id}/drafts");

        $response->assertOk()->assertJsonStructure(['data' => ['brd', 'stories']]);
    }

    public function test_user_can_update_draft_content(): void
    {
        $draft = $this->createDraft();

        $response = $this->actingAs($this->user)
            ->patchJson("/api/features/{$this->feature->id}/drafts/{$draft->id}", [
                'content' => 'Updated content',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', ['id' => $draft->id, 'content' => 'Updated content']);
    }

    public function test_user_can_approve_draft(): void
    {
        $draft = $this->createDraft();

        $response = $this->actingAs($this->user)
            ->postJson("/api/features/{$this->feature->id}/drafts/{$draft->id}/approve", [
                'scores' => [
                    'domain_accuracy' => ['score' => 4, 'tags' => []],
                    'structural_completeness' => ['score' => 5, 'tags' => []],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', ['id' => $draft->id, 'status' => 'approved']);
    }

    public function test_user_cannot_access_another_users_drafts(): void
    {
        $otherProject = Project::factory()->create();
        $otherFeature = Feature::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($this->user)
            ->getJson("/api/features/{$otherFeature->id}/drafts")
            ->assertForbidden();
    }

    public function test_user_cannot_update_draft_from_another_project(): void
    {
        $otherProject = Project::factory()->create();
        $otherFeature = Feature::factory()->create(['project_id' => $otherProject->id]);
        $otherDraft = RequirementDraft::create([
            'feature_id' => $otherFeature->id,
            'type' => 'brd',
            'content' => 'Some content',
            'version' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/features/{$this->feature->id}/drafts/{$otherDraft->id}", [
                'content' => 'Hacked',
            ])
            ->assertForbidden();
    }

    public function test_draft_versioning_increments(): void
    {
        $this->createDraft(['type' => 'brd', 'version' => 1]);
        $this->createDraft(['type' => 'brd', 'version' => 2]);

        $this->assertDatabaseCount('requirement_drafts', 2);
    }
}
