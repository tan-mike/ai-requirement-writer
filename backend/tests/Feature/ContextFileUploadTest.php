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
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'processing']);
        
        $content = "# Project Context\nThis is a test context.";
        $file = UploadedFile::fake()->createWithContent('context.md', $content);
        $path = $file->store('temp_context');

        $job = new ProcessContextFileJob($project, $path);
        $job->handle();

        $project->refresh();
        $this->assertEquals($content, $project->architecture_summary);
        $this->assertEquals('ready', $project->status);
        Storage::disk('local')->assertMissing($path);
    }
}
