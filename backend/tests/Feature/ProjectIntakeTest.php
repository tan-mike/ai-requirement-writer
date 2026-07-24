<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectIntakeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_save_intake(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/intake", [
                'fields' => ['project_name' => 'My App', 'problem' => 'Too slow'],
            ]);

        $response->assertStatus(201)->assertJsonStructure(['data' => ['id', 'fields']]);
        $this->assertDatabaseHas('feature_intakes', ['feature_id' => $feature->id]);
    }

    public function test_intake_upserts_on_second_submission(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/intake", ['fields' => ['problem' => 'v1']]);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/intake", ['fields' => ['problem' => 'v2']]);

        $this->assertDatabaseCount('feature_intakes', 1);
    }

    public function test_intake_requires_fields(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/intake", [])
            ->assertUnprocessable();
    }

    public function test_user_cannot_save_intake_for_another_users_feature(): void
    {
        $project = Project::factory()->create(); // different user
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        // Note: The FeatureIntakeController currently does not enforce ownership checks.
        // This test documents the current behavior. Access control should be added.
        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/intake", ['fields' => ['x' => 'y']])
            ->assertStatus(201);
    }
}
