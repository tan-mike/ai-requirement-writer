<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Jobs\ProcessContextFileJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_file_is_processed_correctly()
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => Project::STATUS_PROCESSING]);
        
        $content = "# Project Context\nThis is a test context.";
        $file = UploadedFile::fake()->createWithContent('context.md', $content);
        $path = $file->store('temp_context');

        $job = new ProcessContextFileJob($project, $path);
        $job->handle();

        $project->refresh();
        $this->assertEquals($content, $project->architecture_summary);
        $this->assertEquals(Project::STATUS_READY, $project->status);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_user_can_create_project_with_context_file()
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'File Project',
            'type' => 'webapp',
            'context_file' => UploadedFile::fake()->create('context.md', 100),
        ]);

        $response->assertStatus(201);
        $project = Project::first();
        $this->assertEquals('File Project', $project->name);
        
        Queue::assertPushed(ProcessContextFileJob::class, function ($job) use ($project) {
            return $job->project->id === $project->id;
        });
    }
}
