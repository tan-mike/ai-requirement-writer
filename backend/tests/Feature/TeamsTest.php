<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\Persona;
use App\Models\ProjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamsTest extends TestCase
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

    public function test_user_can_create_team(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/teams', ['name' => 'Alpha Team']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('teams', ['name' => 'Alpha Team', 'owner_id' => $this->user->id]);
        $this->assertEquals('Alpha Team', $this->user->fresh()->currentTeam->name);
    }

    public function test_user_can_join_team_via_code(): void
    {
        $owner = User::factory()->create();
        $team = Team::create(['name' => 'Beta Team', 'code' => 'JOINME', 'owner_id' => $owner->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/teams/join', ['code' => 'JOINME']);

        $response->assertOk();
        
        $this->user->refresh();
        $this->assertEquals($team->id, $this->user->current_team_id);
    }

    public function test_user_can_share_context_with_team(): void
    {
        $team = Team::create(['name' => 'Gamma Team', 'code' => 'GAMMA1', 'owner_id' => $this->user->id]);
        $this->user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/contexts', [
                'name' => 'Team Context',
                'content' => 'Team Secret',
                'is_team_shared' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('project_contexts', [
            'name' => 'Team Context',
            'team_id' => $team->id,
        ]);
    }

    public function test_team_members_can_see_shared_projects(): void
    {
        $member = User::factory()->create();
        $team = Team::create(['name' => 'Delta Team', 'code' => 'DELTA1', 'owner_id' => $this->user->id]);
        $team->users()->attach([$this->user->id, $member->id]);
        
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'lead_persona_id' => $this->persona->id,
        ]);

        $member->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($member)->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonFragment(['name' => $project->name]);
    }

    public function test_user_can_retroactively_share_private_context(): void
    {
        $team = Team::create(['name' => 'Echo Team', 'code' => 'ECHO11', 'owner_id' => $this->user->id]);
        $this->user->update(['current_team_id' => $team->id]);

        $context = $this->user->projectContexts()->create([
            'name' => 'Private Context',
            'content' => 'My Eyes Only',
            'team_id' => null,
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/contexts/{$context->id}", [
                'content' => 'Everyone can see',
                'is_team_shared' => true,
            ])
            ->assertOk();

        $this->assertEquals($team->id, $context->fresh()->team_id);
    }
}
