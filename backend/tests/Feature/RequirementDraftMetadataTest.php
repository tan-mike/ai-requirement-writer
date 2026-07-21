<?php

namespace Tests\Feature;

use App\Models\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequirementDraftMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_drafts_table_has_metadata_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('requirement_drafts', 'title'));
        $this->assertTrue(Schema::hasColumn('requirement_drafts', 'description'));
    }

    public function test_requirement_draft_model_persists_metadata(): void
    {
        $project = \App\Models\Project::factory()->create();
        $feature = Feature::factory()->create(['project_id' => $project->id]);

        $draft = \App\Models\RequirementDraft::create([
            'feature_id' => $feature->id,
            'type' => 'brd',
            'title' => 'Feature X',
            'description' => 'Requirement for Feature X',
            'content' => 'Content here',
        ]);

        $this->assertEquals('Feature X', $draft->title);
        $this->assertEquals('Requirement for Feature X', $draft->description);

        $freshDraft = $draft->fresh();
        $this->assertEquals('Feature X', $freshDraft->title);
        $this->assertEquals('Requirement for Feature X', $freshDraft->description);
    }
}
