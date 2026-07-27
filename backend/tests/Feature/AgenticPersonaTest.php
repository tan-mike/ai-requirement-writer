<?php

namespace Tests\Feature;

use App\Jobs\ProcessCritiqueJob;
use App\Models\Feature;
use App\Models\Persona;
use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AgenticPersonaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Persona $lead;
    private Persona $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['free_tier' => false]);
        $this->lead = Persona::create([
            'slug' => 'lead_general',
            'name' => 'General PM',
            'role' => 'lead',
            'system_prompt' => 'Lead prompt',
        ]);
        $this->reviewer = Persona::create([
            'slug' => 'reviewer_security',
            'name' => 'Security',
            'role' => 'reviewer',
            'system_prompt' => 'Reviewer prompt',
        ]);
    }

    public function test_can_list_personas(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/personas')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_manage_project_contexts(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/contexts', [
                'name' => 'Legacy Docs',
                'content' => 'Old architecture details',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('project_contexts', [
            'user_id' => $this->user->id,
            'name' => 'Legacy Docs',
        ]);
    }

    public function test_brd_generation_with_reviewers_dispatches_jobs(): void
    {
        Bus::fake();
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $feature = Feature::factory()->create([
            'project_id' => $project->id,
            'lead_persona_id' => $this->lead->id,
        ]);
        $feature->intake()->create(['fields' => ['test' => 'data']]);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('forFeature')->andReturnSelf();
        $mock->shouldReceive('getModel')->andReturn('gemma-4-26b-a4b-it');
        $mock->shouldReceive('streamBrd')->andReturnUsing(function ($sp, $fields, $chat, $ctx, callable $cb) {
            $cb('Draft content');
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/generate/brd", [
                'reviewer_persona_ids' => [$this->reviewer->id],
            ]);

        $response->assertOk();
        
        // Consume stream to trigger callback logic
        ob_start();
        $response->baseResponse->sendContent();
        ob_get_clean();

        $this->assertDatabaseHas('requirement_drafts', [
            'status' => 'reviewing',
            'lead_persona_id' => $this->lead->id,
        ]);

        Bus::assertDispatched(ProcessCritiqueJob::class);
    }

    public function test_synthesis_streams_final_version(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $feature = Feature::factory()->create([
            'project_id' => $project->id,
            'lead_persona_id' => $this->lead->id,
        ]);
        $draft = $feature->drafts()->create([
            'type' => 'brd',
            'content' => 'Original content',
            'status' => 'refining',
            'lead_persona_id' => $this->lead->id,
        ]);

        $mock = $this->mock(GeminiGenerationService::class);
        $mock->shouldReceive('forUser')->andReturnSelf();
        $mock->shouldReceive('forFeature')->andReturnSelf();
        $mock->shouldReceive('streamSynthesis')
            ->andReturnUsing(function ($sp, $od, $critiques, callable $cb) {
                $cb('Final content');
            });

        $response = $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/synthesize");

        $response->assertOk();

        // Consume stream
        ob_start();
        $response->baseResponse->sendContent();
        ob_get_clean();
        
        // The controller leaves draft in 'drafting' state after synthesis
        $this->assertEquals('drafting', $draft->fresh()->status);
    }
}
