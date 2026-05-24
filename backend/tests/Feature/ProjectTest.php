<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Persona;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Persona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['current_team_id' => null]);
        $this->persona = Persona::create(['slug' => 'test', 'name' => 'Test', 'role' => 'lead', 'system_prompt' => '...']);
    }

    public function test_user_can_create_a_project(): void
    {
        $template = Template::factory()->create(['type' => 'webapp']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', [
                'name' => 'My Web App',
                'type' => 'webapp',
                'template_id' => $template->id,
                'lead_persona_id' => $this->persona->id,
                'repository_url' => 'https://github.com/example/repo',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'type', 'status', 'mode']]);
        
        $this->assertDatabaseHas('projects', [
            'name' => 'My Web App',
            'user_id' => $this->user->id
        ]);
    }

    public function test_user_can_list_their_own_projects(): void
    {
        Project::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id
        ]);
        
        // Another user's project
        Project::factory()->create([
            'lead_persona_id' => $this->persona->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_a_project(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', $project->name);
    }

    public function test_user_cannot_view_another_users_project(): void
    {
        $otherUser = User::factory()->create(['current_team_id' => null]);
        $project = Project::factory()->create([
            'user_id' => $otherUser->id,
            'lead_persona_id' => $this->persona->id,
            'team_id' => null, // explicitly private
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_user_can_delete_their_project(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'lead_persona_id' => $this->persona->id
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
