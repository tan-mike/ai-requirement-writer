<?php

namespace Tests\Feature;

use App\Models\DraftCritique;
use App\Models\Persona;
use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftCritiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_draft_has_critiques_relationship(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $draft = RequirementDraft::create([
            'project_id' => $project->id,
            'type' => 'brd',
            'content' => 'Test content',
            'version' => 1,
            'status' => 'draft',
        ]);

        $persona = Persona::create([
            'slug' => 'tester',
            'name' => 'Tester',
            'role' => 'reviewer',
            'system_prompt' => 'Test',
        ]);

        $critique = DraftCritique::create([
            'requirement_draft_id' => $draft->id,
            'persona_id' => $persona->id,
            'content' => 'Great work!',
            'status' => 'completed',
        ]);

        $draft->refresh();

        $this->assertCount(1, $draft->critiques);
        $this->assertEquals('Great work!', $draft->critiques->first()->content);
        $this->assertEquals($draft->id, $critique->draft->id);
        $this->assertEquals($persona->id, $critique->persona->id);
    }
}
