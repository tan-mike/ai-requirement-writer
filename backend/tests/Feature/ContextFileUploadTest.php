<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project_with_repository_url()
    {
        config(['projects.creation_disabled' => false]);
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Repo Project',
            'type' => 'webapp',
            'repository_url' => 'https://github.com/example/repo',
        ]);

        $response->assertStatus(201);
        $project = Project::first();
        $this->assertEquals('Repo Project', $project->name);
    }

    public function test_user_can_create_project_without_repository()
    {
        config(['projects.creation_disabled' => false]);
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Simple Project',
            'type' => 'webapp',
        ]);

        $response->assertStatus(201);
        $project = Project::first();
        $this->assertEquals('Simple Project', $project->name);
        $this->assertEquals('ready', $project->status);
    }
}
