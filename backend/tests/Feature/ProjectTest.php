<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_a_project(): void
    {
        $template = Template::factory()->create(['type' => 'webapp']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', [
                'name' => 'My Web App',
                'type' => 'webapp',
                'template_id' => $template->id,
                'repository_url' => 'https://github.com/example/repo',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'type', 'status', 'mode']]);

        $this->assertDatabaseHas('projects', [
            'name' => 'My Web App',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_list_their_own_projects(): void
    {
        Project::factory()->count(2)->create(['user_id' => $this->user->id]);
        Project::factory()->create(); // another user's project

        $response = $this->actingAs($this->user)
            ->getJson('/api/projects');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_their_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $project->id);
    }

    public function test_user_cannot_view_another_users_project(): void
    {
        $project = Project::factory()->create(); // different user

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertForbidden();
    }

    public function test_user_can_delete_their_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
