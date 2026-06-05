<?php
namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectKnowledgeModule;
use App\Services\MemoryGovernorService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MemoryGovernorTest extends TestCase {
    use RefreshDatabase;

    public function test_it_retrieves_relevant_modules() {
        $project = Project::factory()->create();
        ProjectKnowledgeModule::create([
            'project_id' => $project->id,
            'title' => 'Auth Flow',
            'type' => 'feature',
            'content' => 'Users log in via OAuth.',
            'status' => 'active'
        ]);

        // Fail expected: Class MemoryGovernorService not found
        $service = new MemoryGovernorService();
        $context = $service->getRelevantContext($project, 'How does auth work?');

        $this->assertCount(1, $context);
        $this->assertEquals('Auth Flow', $context->first()->title);

        $formatted = $service->formatContextForPrompt($context);
        $this->assertStringContainsString('### MODULE: Auth Flow (feature)', $formatted);
        $this->assertStringContainsString('Users log in via OAuth.', $formatted);
    }

    public function test_it_retrieves_committed_draft_content()
    {
        $project = \App\Models\Project::factory()->create();
        
        // Simulate a committed draft content
        $content = 'Feature X: User can upload images.';
        
        // Directly create the module as if it was committed
        \App\Models\ProjectKnowledgeModule::create([
            'project_id' => $project->id,
            'title' => 'BRD V1',
            'type' => 'requirement',
            'content' => $content,
            'status' => 'active'
        ]);

        $service = new \App\Services\MemoryGovernorService();
        $context = $service->getRelevantContext($project, 'How do I handle image uploads?');

        $this->assertCount(1, $context);
        $this->assertStringContainsString('upload images', $context->first()->content);
    }
}
