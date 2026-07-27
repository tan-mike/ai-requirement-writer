<?php

namespace Tests\Feature;

use App\Models\DraftScore;
use App\Models\Feature;
use App\Models\Persona;
use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_scoring_config_requires_auth(): void
    {
        $this->getJson('/api/scoring-config')->assertUnauthorized();
    }

    public function test_scoring_config_returns_rubric(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/scoring-config');

        $response->assertOk()
            ->assertJsonPath('data.low_score_threshold', 2)
            ->assertJsonStructure([
                'data' => [
                    'low_score_threshold',
                    'criteria' => [
                        'domain_accuracy' => ['label', 'question', 'anchors', 'tags'],
                        'structural_completeness' => ['label', 'question', 'anchors', 'tags'],
                    ],
                ],
            ]);
    }

    private Feature $feature;
    private Persona $persona;

    private function makeFeature(): Feature
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        return Feature::factory()->create(['project_id' => $project->id]);
    }

    private function makeDraft(Feature $feature, array $attrs = []): RequirementDraft
    {
        // lead_persona_id has a foreign key to personas, so a real Persona must exist.
        $this->persona = Persona::create([
            'slug' => 'lead-ba',
            'name' => 'Lead BA',
            'role' => 'lead',
            'system_prompt' => 'You are a business analyst.',
        ]);

        return RequirementDraft::create(array_merge([
            'feature_id' => $feature->id,
            'user_id' => $this->user->id,
            'type' => 'brd',
            'content' => 'Some content',
            'version' => 1,
            'status' => 'drafting',
            'lead_persona_id' => $this->persona->id,
            'reviewer_persona_ids' => [2, 3],
            'context_ids' => [10, 11],
            'model' => 'gemma-4-26b-a4b-it',
        ], $attrs));
    }

    private function validScores(): array
    {
        return [
            'domain_accuracy' => ['score' => 4, 'tags' => []],
            'structural_completeness' => ['score' => 5, 'tags' => []],
        ];
    }

    public function test_approve_requires_scores(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scores']);
    }

    public function test_approve_rejects_missing_criterion(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $scores = $this->validScores();
        unset($scores['structural_completeness']);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $scores])
            ->assertStatus(422);
    }

    public function test_approve_rejects_unknown_criterion(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $scores = $this->validScores();
        $scores['bogus'] = ['score' => 3, 'tags' => []];

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $scores])
            ->assertStatus(422);
    }

    public function test_approve_rejects_out_of_range_score(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $scores = $this->validScores();
        $scores['domain_accuracy']['score'] = 9;

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $scores])
            ->assertStatus(422);
    }

    public function test_low_score_requires_tag(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $scores = $this->validScores();
        $scores['domain_accuracy'] = ['score' => 2, 'tags' => []];

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $scores])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scores.domain_accuracy.tags']);
    }

    public function test_tag_from_wrong_criterion_is_rejected(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $scores = $this->validScores();
        // 'missing_section' belongs to structural_completeness, not domain_accuracy
        $scores['domain_accuracy'] = ['score' => 2, 'tags' => ['missing_section']];

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $scores])
            ->assertStatus(422);
    }

    public function test_approve_with_valid_scores_creates_score_and_approves(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature);

        $scores = $this->validScores();
        $scores['structural_completeness'] = ['score' => 2, 'tags' => ['missing_section']];

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $scores])
            ->assertOk();

        $this->assertDatabaseHas('requirement_drafts', ['id' => $draft->id, 'status' => 'approved']);

        $score = DraftScore::where('requirement_draft_id', $draft->id)->first();
        $this->assertNotNull($score);
        $this->assertEquals(3.0, (float) $score->overall_score); // (4 + 2) / 2
        $this->assertEquals(['missing_section'], $score->criterion_scores['structural_completeness']['tags']);
        $this->assertEquals([
            'lead_persona_id' => $this->persona->id,
            'reviewer_persona_ids' => [2, 3],
            'context_ids' => [10, 11],
            'model' => 'gemma-4-26b-a4b-it',
            'draft_type' => 'brd',
            'draft_version' => 1,
        ], $score->recipe_snapshot);
    }

    public function test_cannot_reapprove_or_rescore(): void
    {
        $feature = $this->makeFeature();
        $draft = $this->makeDraft($feature, ['status' => 'approved']);

        $this->actingAs($this->user)
            ->postJson("/api/features/{$feature->id}/drafts/{$draft->id}/approve", ['scores' => $this->validScores()])
            ->assertStatus(422);

        $this->assertDatabaseCount('draft_scores', 0);
    }
}
