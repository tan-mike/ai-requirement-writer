<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manually_complete_their_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'status' => Project::STATUS_READY,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/complete");

        $response->assertStatus(200);
        $this->assertEquals(Project::STATUS_COMPLETE, $project->fresh()->status);
    }

    public function test_user_cannot_complete_someone_elses_project()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $otherUser->id,
            'status' => Project::STATUS_READY,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/complete");

        $response->assertStatus(403);
        $this->assertNotEquals(Project::STATUS_COMPLETE, $project->fresh()->status);
    }

    public function test_unauthenticated_user_cannot_complete_project()
    {
        $project = Project::factory()->create([
            'status' => Project::STATUS_READY,
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/complete");

        $response->assertStatus(401);
        $this->assertNotEquals(Project::STATUS_COMPLETE, $project->fresh()->status);
    }
}
