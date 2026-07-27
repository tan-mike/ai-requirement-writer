<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Project;
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
}
