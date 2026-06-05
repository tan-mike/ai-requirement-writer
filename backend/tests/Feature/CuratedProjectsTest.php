<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuratedProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_only_sees_curated_projects()
    {
        $user = User::factory()->create(['role' => 'member']);
        
        // Curated projects
        Project::factory()->count(2)->create(['is_curated' => true]);
        // Non-curated project
        Project::factory()->create(['is_curated' => false]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['is_curated' => true]);
        $response->assertJsonMissing(['is_curated' => false]);
    }

    public function test_admin_user_sees_all_relevant_projects()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Own project
        Project::factory()->create(['user_id' => $admin->id, 'is_curated' => false]);
        // Curated project
        Project::factory()->create(['is_curated' => true]);
        // Someone else's non-curated project
        Project::factory()->create(['is_curated' => false]);

        $response = $this->actingAs($admin)->getJson('/api/projects');

        $response->assertStatus(200);
        // Admin index currently shows own projects + curated. 
        // Wait, index currently shows own + team. 
        // I need to be careful about what I expect here.
        // If I change index to ONLY curated for members, admin should probably still see their own.
        $response->assertJsonCount(2, 'data');
    }

    public function test_regular_user_cannot_create_project_when_disabled()
    {
        config(['projects.creation_disabled' => true]);
        $user = User::factory()->create(['role' => 'member']);
        $persona = \App\Models\Persona::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New Project',
            'type' => 'webapp',
            'mode' => 'template',
            'lead_persona_id' => $persona->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_user_can_create_project_even_when_disabled()
    {
        config(['projects.creation_disabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $persona = \App\Models\Persona::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/projects', [
            'name' => 'Admin Project',
            'type' => 'webapp',
            'mode' => 'template',
            'lead_persona_id' => $persona->id,
        ]);

        $response->assertStatus(201);
    }
}
