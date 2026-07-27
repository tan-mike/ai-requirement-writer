# Draft Output Scoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require reviewers to score each AI-generated draft on two criteria (1–5) with per-criterion issue tags before approval, persisting the score and a frozen recipe snapshot for later quality benchmarking.

**Architecture:** A config-driven rubric (`config/scoring.php`) defines the criteria and their scoped tag sets, exposed to the frontend via `GET /api/scoring-config`. The existing `approve` endpoint is extended to require a `scores` payload and, in one DB transaction, insert a `draft_scores` row (criterion scores + recipe snapshot) and approve the draft. The frontend opens a `ScoringModal` from the DraftEditor's Approve button.

**Tech Stack:** Laravel 11 (Eloquent, Sanctum, PHPUnit feature tests), Next.js App Router + React (TypeScript, Vitest + Testing Library), Tailwind v4 design tokens.

**Spec:** `docs/superpowers/specs/2026-07-24-draft-output-scoring-design.md`

---

## File Structure

**Backend — create:**
- `backend/database/migrations/2026_07_24_000001_create_draft_scores_table.php` — new `draft_scores` table
- `backend/database/migrations/2026_07_24_000002_add_recipe_columns_to_requirement_drafts_table.php` — `context_ids`, `model` columns
- `backend/config/scoring.php` — rubric config (criteria, tags, threshold)
- `backend/app/Models/DraftScore.php` — score model
- `backend/app/Http/Controllers/ScoringConfigController.php` — read-only config endpoint
- `backend/tests/Feature/DraftScoreTest.php` — scoring feature tests

**Backend — modify:**
- `backend/app/Models/RequirementDraft.php` — fillable + `score()` relation
- `backend/app/Services/GeminiGenerationService.php` — `getModel()` accessor
- `backend/app/Http/Controllers/GenerationController.php` — persist `context_ids` + `model` at draft creation
- `backend/app/Http/Controllers/RequirementDraftController.php` — scoring validation + transactional approve
- `backend/routes/api.php` — `GET /scoring-config` route
- `backend/tests/Feature/RequirementDraftTest.php` — update existing approve test to send scores

**Frontend — create:**
- `frontend/components/ScoringModal.tsx` — scoring modal component
- `frontend/tests/components/ScoringModal.test.tsx` — modal tests

**Frontend — modify:**
- `frontend/components/DraftEditor.tsx` — open modal from Approve button

---

## Task 1: Database Schema

**Files:**
- Create: `backend/database/migrations/2026_07_24_000001_create_draft_scores_table.php`
- Create: `backend/database/migrations/2026_07_24_000002_add_recipe_columns_to_requirement_drafts_table.php`

- [ ] **Step 1: Create the `draft_scores` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_draft_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('criterion_scores');
            $table->decimal('overall_score', 2, 1);
            $table->json('recipe_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_scores');
    }
};
```

- [ ] **Step 2: Create the `requirement_drafts` columns migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->json('context_ids')->nullable()->after('reviewer_persona_ids');
            $table->string('model')->nullable()->after('context_ids');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropColumn(['context_ids', 'model']);
        });
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `cd backend && php artisan migrate`
Expected: `MIGRATED 2026_07_24_000001_create_draft_scores_table` and `..._000002_add_recipe_columns...`

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_07_24_000001_create_draft_scores_table.php backend/database/migrations/2026_07_24_000002_add_recipe_columns_to_requirement_drafts_table.php
git commit -m "feat(scoring): add draft_scores table and recipe columns"
```

---

## Task 2: Scoring Config

**Files:**
- Create: `backend/config/scoring.php`

- [ ] **Step 1: Create the rubric config**

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Low-Score Threshold
    |--------------------------------------------------------------------------
    |
    | A criterion scored at or below this value requires at least one issue
    | tag from that criterion's tag set before the draft can be approved.
    */
    'low_score_threshold' => 2,

    /*
    |--------------------------------------------------------------------------
    | Scoring Criteria
    |--------------------------------------------------------------------------
    |
    | Each criterion is scored independently on a 1-5 scale. The tag set is
    | scoped per criterion — a tag is only valid for its own criterion.
    */
    'criteria' => [
        'domain_accuracy' => [
            'label' => 'Domain Accuracy',
            'question' => 'How factually accurate is this document in relation to the provided source context, and is it completely free of hallucinated features or assumptions?',
            'anchors' => [
                1 => 'Highly inaccurate / major hallucinations',
                5 => 'Perfectly grounded to the provided context',
            ],
            'tags' => [
                'hallucinated_feature' => 'Hallucinated feature',
                'contradicts_context' => 'Contradicts source context',
                'wrong_domain_term' => 'Wrong domain terminology',
                'unsupported_assumption' => 'Unsupported assumption',
                'other' => 'Other',
            ],
        ],
        'structural_completeness' => [
            'label' => 'Structural Completeness',
            'question' => 'How well does this output adhere to the required formatting rules, including all mandatory sections (e.g., Executive Summary, User Stories)?',
            'anchors' => [
                1 => 'Missing critical sections or formatting',
                5 => 'Structurally perfect and fully compliant',
            ],
            'tags' => [
                'missing_section' => 'Missing mandatory section',
                'wrong_format' => 'Wrong format',
                'incomplete_items' => 'Incomplete items',
                'poor_organization' => 'Poor organization',
                'other' => 'Other',
            ],
        ],
    ],
];
```

- [ ] **Step 2: Verify config loads**

Run: `cd backend && php artisan tinker --execute="echo count(config('scoring.criteria'));"`
Expected: `2`

- [ ] **Step 3: Commit**

```bash
git add backend/config/scoring.php
git commit -m "feat(scoring): add config-driven scoring rubric"
```

---

## Task 3: DraftScore Model + RequirementDraft Relations

**Files:**
- Create: `backend/app/Models/DraftScore.php`
- Modify: `backend/app/Models/RequirementDraft.php`

- [ ] **Step 1: Create the `DraftScore` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftScore extends Model
{
    protected $fillable = [
        'requirement_draft_id',
        'user_id',
        'criterion_scores',
        'overall_score',
        'recipe_snapshot',
    ];

    protected $casts = [
        'criterion_scores' => 'array',
        'recipe_snapshot' => 'array',
        'overall_score' => 'decimal:1',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(RequirementDraft::class, 'requirement_draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Add fillable columns and `score()` relation to `RequirementDraft`**

In `backend/app/Models/RequirementDraft.php`:

1. Add the `HasOne` import. The model currently imports only `BelongsTo` and `HasMany`, so add this line after `use Illuminate\Database\Eloquent\Relations\HasMany;`:
```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

2. Add `'context_ids'` and `'model'` to the `$fillable` array (after `'reviewer_persona_ids'`).

3. Add `'context_ids' => 'array'` to `$casts`.

4. Add the relation method after `critiques()`:

```php
    public function score(): HasOne
    {
        return $this->hasOne(DraftScore::class);
    }
```

- [ ] **Step 3: Verify the new columns exist**

Run: `cd backend && php artisan tinker --execute="echo collect(Schema::getColumns('requirement_drafts'))->pluck('name')->intersect(['context_ids','model'])->implode(',');"`
Expected: `context_ids,model` (both columns present). Authoritative verification happens when the full test suite runs in Task 6.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Models/DraftScore.php backend/app/Models/RequirementDraft.php
git commit -m "feat(scoring): add DraftScore model and draft relations"
```

---

## Task 4: Capture Recipe at Generation Time

**Files:**
- Modify: `backend/app/Services/GeminiGenerationService.php`
- Modify: `backend/app/Http/Controllers/GenerationController.php`

- [ ] **Step 1: Add a `getModel()` accessor to `GeminiGenerationService`**

In `backend/app/Services/GeminiGenerationService.php`, add directly after the existing `setModel()` method (around line 84):

```php
    /**
     * Get the model identifier used for generation.
     */
    public function getModel(): string
    {
        return $this->model;
    }
```

- [ ] **Step 2: Persist `context_ids` and `model` at draft creation**

In `backend/app/Http/Controllers/GenerationController.php`, update the `$feature->drafts()->create([...])` call (around line 83) to add the two fields. The `$contexts` collection is already computed above it:

```php
        $draft = $feature->drafts()->create([
            'user_id' => $request->user()->id,
            'type' => $type,
            'version' => $this->nextVersion($feature, $type, $request->user()->id),
            'title' => $request->title,
            'description' => $request->description,
            'content' => '',
            'status' => 'drafting',
            'lead_persona_id' => $leadPersona->id,
            'reviewer_persona_ids' => $reviewers->pluck('id')->toArray(),
            'context_ids' => $contexts->pluck('id')->values()->all(),
            'model' => $this->gemini->getModel(),
        ]);
```

- [ ] **Step 3: Verify no syntax errors**

Run: `cd backend && php -l app/Http/Controllers/GenerationController.php && php -l app/Services/GeminiGenerationService.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Update generation-endpoint test mocks to stub `getModel()`**

Several feature tests mock `GeminiGenerationService` with Mockery and exercise the `generate` endpoint (`/generate/brd|stories|spec`): three in `GenerationTest`, plus the `streamBrd` test in `ChatTest` and the `streamBrd` test in `AgenticPersonaTest`. Mockery mocks are strict: calling an un-stubbed method throws `BadMethodCallException`. Because the controller now calls `$this->gemini->getModel()` during draft creation, each of those mocks must stub it. (Tests that only hit `synthesize`, discovery chat, or `generateCritique` do **not** call `getModel()` and need no change.)

In each affected mock, add this line immediately after `$mock->shouldReceive('forFeature')->andReturnSelf();`:

```php
        $mock->shouldReceive('getModel')->andReturn('gemma-4-26b-a4b-it');
```

- [ ] **Step 5: Run the generation-related tests to verify no regression**

Run: `cd backend && php artisan test --filter="GenerationTest|ChatTest|AgenticPersonaTest"`
Expected: PASS (all matched tests).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/GeminiGenerationService.php backend/app/Http/Controllers/GenerationController.php backend/tests/Feature/GenerationTest.php
git commit -m "feat(scoring): capture context_ids and model on draft creation"
```

---

## Task 5: Scoring Config Endpoint

**Files:**
- Create: `backend/app/Http/Controllers/ScoringConfigController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/DraftScoreTest.php` (created here, extended in Task 6)

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/DraftScoreTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test --filter=DraftScoreTest`
Expected: FAIL — route `/api/scoring-config` not found (404 instead of 200).

- [ ] **Step 3: Create the controller**

Create `backend/app/Http/Controllers/ScoringConfigController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ScoringConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => config('scoring')]);
    }
}
```

- [ ] **Step 4: Register the route**

In `backend/routes/api.php`, inside the `Route::middleware(['auth:sanctum', 'verified'])->group(function () { ... })` block (e.g. just before the `// Drafts` comment around line 94), add:

```php
    // Scoring rubric config
    Route::get('/scoring-config', [\App\Http\Controllers\ScoringConfigController::class, '__invoke']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `cd backend && php artisan test --filter=DraftScoreTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/ScoringConfigController.php backend/routes/api.php backend/tests/Feature/DraftScoreTest.php
git commit -m "feat(scoring): add scoring-config endpoint"
```

---

## Task 6: Approve Endpoint — Scoring Validation + Transaction

**Files:**
- Modify: `backend/app/Http/Controllers/RequirementDraftController.php`
- Test: `backend/tests/Feature/DraftScoreTest.php` (extend)

- [ ] **Step 1: Write the failing tests**

Append these tests (and imports) to `backend/tests/Feature/DraftScoreTest.php`. Add to the top imports: `use App\Models\Persona;`, `use App\Models\RequirementDraft;`, and `use App\Models\DraftScore;`. Add a helper and tests inside the class:

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && php artisan test --filter=DraftScoreTest`
Expected: the new approve tests FAIL (endpoint currently approves without scores).

- [ ] **Step 3: Implement the scoring approve logic**

Replace the `approve()` method in `backend/app/Http/Controllers/RequirementDraftController.php` and add the needed imports. Add to the top of the file:

```php
use App\Models\DraftScore;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
```

Replace the `approve()` method with:

```php
    public function approve(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        if ($feature->status === Feature::STATUS_COMPLETED) {
            return response()->json(['message' => 'Cannot approve drafts for a finalized feature.'], 422);
        }

        if ($draft->status === 'approved') {
            return response()->json(['message' => 'Draft has already been approved.'], 422);
        }

        $criteria = config('scoring.criteria');
        $threshold = (int) config('scoring.low_score_threshold', 2);

        $rules = [
            'scores' => ['required', 'array:' . implode(',', array_keys($criteria))],
        ];
        foreach ($criteria as $key => $criterion) {
            $rules["scores.{$key}.score"] = ['required', 'integer', 'min:1', 'max:5'];
            $rules["scores.{$key}.tags"] = ['array'];
            $rules["scores.{$key}.tags.*"] = [Rule::in(array_keys($criterion['tags']))];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $criteria, $threshold) {
            foreach ($criteria as $key => $criterion) {
                $score = $request->input("scores.{$key}.score");
                $tags = $request->input("scores.{$key}.tags", []);
                if (is_int($score) && $score <= $threshold && count($tags) === 0) {
                    $validator->errors()->add(
                        "scores.{$key}.tags",
                        "Select at least one issue tag for {$criterion['label']}."
                    );
                }
            }
        });

        $validator->validate();

        $scores = $request->input('scores');

        try {
            DB::transaction(function () use ($draft, $scores, $request) {
                DraftScore::create([
                    'requirement_draft_id' => $draft->id,
                    'user_id' => $request->user()->id,
                    'criterion_scores' => collect($scores)->map(fn ($c) => [
                        'score' => (int) $c['score'],
                        'tags' => $c['tags'] ?? [],
                    ])->all(),
                    'overall_score' => round(collect($scores)->avg(fn ($c) => $c['score']), 1),
                    'recipe_snapshot' => [
                        'lead_persona_id' => $draft->lead_persona_id,
                        'reviewer_persona_ids' => $draft->reviewer_persona_ids,
                        'context_ids' => $draft->context_ids,
                        'model' => $draft->model,
                        'draft_type' => $draft->type,
                        'draft_version' => $draft->version,
                    ],
                ]);

                $draft->approve();
            });
        } catch (QueryException $e) {
            // SQLSTATE class 23 = integrity constraint violation (unique race)
            if (str_starts_with((string) ($e->errorInfo[0] ?? ''), '23')) {
                return response()->json(['message' => 'Draft has already been approved.'], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $draft->fresh()]);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd backend && php artisan test --filter=DraftScoreTest`
Expected: PASS (all DraftScoreTest tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/RequirementDraftController.php backend/tests/Feature/DraftScoreTest.php
git commit -m "feat(scoring): require criterion scores and tags to approve a draft"
```

---

## Task 7: Update Existing Approve Test

**Files:**
- Modify: `backend/tests/Feature/RequirementDraftTest.php`

- [ ] **Step 1: Update `test_user_can_approve_draft` to send a valid scores payload**

The existing test posts with no body and will now 422. Replace it with:

```php
    public function test_user_can_approve_draft(): void
    {
        $draft = $this->createDraft();

        $response = $this->actingAs($this->user)
            ->postJson("/api/features/{$this->feature->id}/drafts/{$draft->id}/approve", [
                'scores' => [
                    'domain_accuracy' => ['score' => 4, 'tags' => []],
                    'structural_completeness' => ['score' => 5, 'tags' => []],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('requirement_drafts', ['id' => $draft->id, 'status' => 'approved']);
    }
```

Note: `createDraft()` in this file sets `status => 'draft'`; the approve guard checks for `'approved'`, so a `'draft'` status still passes. No other change needed.

- [ ] **Step 2: Run the full backend test suite**

Run: `cd backend && php artisan test`
Expected: PASS — no regressions. (If any other test calls the approve endpoint without scores, update it the same way; a workspace search shows only this one does.)

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/RequirementDraftTest.php
git commit -m "test(scoring): update approve test to include scores payload"
```

---

## Task 8: ScoringModal Component

**Files:**
- Create: `frontend/components/ScoringModal.tsx`
- Test: `frontend/tests/components/ScoringModal.test.tsx`

- [ ] **Step 1: Write the failing test**

Create `frontend/tests/components/ScoringModal.test.tsx`:

```tsx
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import ScoringModal from '@/components/ScoringModal'
import { apiClient } from '@/lib/api'

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
  default: { get: vi.fn(), post: vi.fn() },
}))

const configResponse = {
  data: {
    low_score_threshold: 2,
    criteria: {
      domain_accuracy: {
        label: 'Domain Accuracy',
        question: 'How accurate?',
        anchors: { '1': 'bad', '5': 'good' },
        tags: { hallucinated_feature: 'Hallucinated feature' },
      },
      structural_completeness: {
        label: 'Structural Completeness',
        question: 'How complete?',
        anchors: { '1': 'bad', '5': 'good' },
        tags: { missing_section: 'Missing section' },
      },
    },
  },
}

beforeEach(() => {
  vi.clearAllMocks()
  ;(apiClient.get as ReturnType<typeof vi.fn>).mockResolvedValue(configResponse)
  ;(apiClient.post as ReturnType<typeof vi.fn>).mockResolvedValue({ data: {} })
})

function renderModal() {
  return render(
    <ScoringModal featureId="1" draftId={42} onClose={() => {}} onApproved={() => {}} />
  )
}

describe('ScoringModal', () => {
  it('renders criteria from config', async () => {
    renderModal()
    expect(await screen.findByText('Domain Accuracy')).toBeInTheDocument()
    expect(screen.getByText('Structural Completeness')).toBeInTheDocument()
  })

  it('disables submit until every criterion is scored', async () => {
    renderModal()
    await screen.findByText('Domain Accuracy')

    const submit = screen.getByRole('button', { name: /score & approve/i })
    expect(submit).toBeDisabled()

    // Score only one criterion — still disabled
    fireEvent.click(screen.getAllByRole('radio', { name: '4' })[0])
    expect(submit).toBeDisabled()
  })

  it('requires a tag when a criterion scores at or below the threshold', async () => {
    renderModal()
    await screen.findByText('Domain Accuracy')

    // Give domain_accuracy a low score (1) and structural a high score (5)
    fireEvent.click(screen.getAllByRole('radio', { name: '1' })[0])
    fireEvent.click(screen.getAllByRole('radio', { name: '5' })[1])

    const submit = screen.getByRole('button', { name: /score & approve/i })
    expect(screen.getByText(/select at least one/i)).toBeInTheDocument()
    expect(submit).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Hallucinated feature' }))
    expect(submit).toBeEnabled()
  })

  it('submits the scores payload on approve', async () => {
    const onApproved = vi.fn()
    render(
      <ScoringModal featureId="1" draftId={42} onClose={() => {}} onApproved={onApproved} />
    )
    await screen.findByText('Domain Accuracy')

    fireEvent.click(screen.getAllByRole('radio', { name: '4' })[0])
    fireEvent.click(screen.getAllByRole('radio', { name: '5' })[1])
    fireEvent.click(screen.getByRole('button', { name: /score & approve/i }))

    await waitFor(() => {
      expect(apiClient.post).toHaveBeenCalledWith(
        '/features/1/drafts/42/approve',
        {
          scores: {
            domain_accuracy: { score: 4, tags: [] },
            structural_completeness: { score: 5, tags: [] },
          },
        }
      )
    })
    expect(onApproved).toHaveBeenCalled()
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd frontend && npm run test:run -- ScoringModal`
Expected: FAIL — cannot find `@/components/ScoringModal`.

- [ ] **Step 3: Create the component**

Create `frontend/components/ScoringModal.tsx`:

```tsx
'use client'
import { useEffect, useState } from 'react'
import { apiClient } from '@/lib/api'
import InfoTooltip from '@/components/InfoTooltip'

interface CriterionConfig {
  label: string
  question: string
  anchors: Record<string, string>
  tags: Record<string, string>
}

interface ScoringConfig {
  low_score_threshold: number
  criteria: Record<string, CriterionConfig>
}

interface CriterionScore {
  score: number | null
  tags: string[]
}

interface Props {
  featureId: string
  draftId: number
  onClose: () => void
  onApproved: () => void
}

export default function ScoringModal({ featureId, draftId, onClose, onApproved }: Props) {
  const [config, setConfig] = useState<ScoringConfig | null>(null)
  const [loadError, setLoadError] = useState('')
  const [scores, setScores] = useState<Record<string, CriterionScore>>({})
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: ScoringConfig }>('/scoring-config')
      .then(res => {
        setConfig(res.data)
        const init: Record<string, CriterionScore> = {}
        for (const key of Object.keys(res.data.criteria)) init[key] = { score: null, tags: [] }
        setScores(init)
      })
      .catch(() => setLoadError('Could not load the scoring rubric. Please retry.'))
  }, [])

  function setScore(key: string, value: number) {
    setScores(prev => ({ ...prev, [key]: { score: value, tags: prev[key]?.tags ?? [] } }))
  }

  function toggleTag(key: string, tag: string) {
    setScores(prev => {
      const cur = prev[key]?.tags ?? []
      const tags = cur.includes(tag) ? cur.filter(t => t !== tag) : [...cur, tag]
      return { ...prev, [key]: { ...prev[key], tags } }
    })
  }

  const threshold = config?.low_score_threshold ?? 2
  const needsTags = (s: CriterionScore) => s.score !== null && s.score <= threshold

  const isValid = config !== null && Object.keys(config.criteria).every(key => {
    const s = scores[key]
    if (!s || s.score === null) return false
    if (needsTags(s) && s.tags.length === 0) return false
    return true
  })

  async function handleSubmit() {
    if (!config || !isValid) return
    setSubmitting(true)
    setSubmitError('')
    try {
      const payload = {
        scores: Object.fromEntries(
          Object.entries(scores).map(([k, v]) => [k, { score: v.score, tags: v.tags }])
        ),
      }
      await apiClient.post(`/features/${featureId}/drafts/${draftId}/approve`, payload)
      onApproved()
    } catch (err) {
      setSubmitError(err instanceof Error ? err.message : 'Approval failed')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-background/80 backdrop-blur-sm z-[100] flex items-center justify-center p-6 animate-in fade-in duration-200">
      <div className="bg-card border border-border rounded-2xl p-8 max-w-lg w-full shadow-2xl animate-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
        <h3 className="text-xl font-bold mb-1">Score this draft</h3>
        <p className="text-sm text-muted-foreground mb-6">
          Your rating is required before approval and feeds future quality improvements.
        </p>

        {loadError && (
          <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-4">
            <p className="text-red-700 dark:text-red-400 text-sm font-medium">{loadError}</p>
          </div>
        )}

        {config && (
          <div className="space-y-6">
            {Object.entries(config.criteria).map(([key, criterion]) => {
              const s = scores[key] ?? { score: null, tags: [] }
              const tooltip = `${criterion.question} (1 = ${criterion.anchors['1']}; 5 = ${criterion.anchors['5']})`
              return (
                <div key={key} className="border border-border rounded-xl p-4">
                  <div className="flex items-center mb-3">
                    <span className="text-sm font-bold">{criterion.label}</span>
                    <InfoTooltip text={tooltip} />
                  </div>

                  <div className="flex gap-1.5" role="radiogroup" aria-label={criterion.label}>
                    {[1, 2, 3, 4, 5].map(n => (
                      <button
                        key={n}
                        type="button"
                        role="radio"
                        aria-checked={s.score === n}
                        aria-label={String(n)}
                        onClick={() => setScore(key, n)}
                        className={`w-9 h-9 rounded-lg text-sm font-bold border transition-all ${
                          s.score === n
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-surface border-border hover:border-primary/50'
                        }`}
                      >
                        {n}
                      </button>
                    ))}
                  </div>

                  {needsTags(s) && (
                    <div className="mt-3">
                      <p className="text-xs font-semibold text-muted-foreground mb-2">
                        What went wrong? <span className="text-red-500">Select at least one.</span>
                      </p>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(criterion.tags).map(([tagKey, tagLabel]) => {
                          const active = s.tags.includes(tagKey)
                          return (
                            <button
                              key={tagKey}
                              type="button"
                              onClick={() => toggleTag(key, tagKey)}
                              className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-all ${
                                active
                                  ? 'bg-primary/10 border-primary text-primary'
                                  : 'bg-surface border-border text-muted-foreground hover:border-primary/50'
                              }`}
                            >
                              {tagLabel}
                            </button>
                          )
                        })}
                      </div>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {submitError && (
          <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mt-4">
            <p className="text-red-700 dark:text-red-400 text-sm font-medium">{submitError}</p>
          </div>
        )}

        <div className="flex gap-3 mt-8">
          <button onClick={onClose} type="button" className="btn-secondary flex-1 py-3">Cancel</button>
          <button
            onClick={handleSubmit}
            type="button"
            disabled={!isValid || submitting || !!loadError}
            className="btn-primary flex-1 py-3 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting ? 'Approving...' : 'Score & Approve'}
          </button>
        </div>
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd frontend && npm run test:run -- ScoringModal`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/components/ScoringModal.tsx frontend/tests/components/ScoringModal.test.tsx
git commit -m "feat(scoring): add ScoringModal component"
```

---

## Task 9: Wire ScoringModal into DraftEditor

**Files:**
- Modify: `frontend/components/DraftEditor.tsx`

- [ ] **Step 1: Open the modal from the Approve button**

In `frontend/components/DraftEditor.tsx`:

1. Add the import after the existing imports:
```tsx
import ScoringModal from '@/components/ScoringModal'
```

2. Add modal state alongside the other `useState` calls (and remove the now-unused `approving` state and `handleApprove` function):
```tsx
  const [showScoring, setShowScoring] = useState(false)
```
Delete the `const [approving, setApproving] = useState(false)` line and the entire `async function handleApprove() { ... }` block.

3. Replace the Approve button (the second button in the `!readOnly` block) with:
```tsx
            <button onClick={() => setShowScoring(true)}
            className="btn-primary flex-1 py-2.5 shadow-lg shadow-primary/20"
            >
            Approve & Finalize
            </button>
```

4. Render the modal just before the final closing `</div>` of the component's return (after the `!readOnly && (...)` block):
```tsx
      {showScoring && (
        <ScoringModal
          featureId={featureId}
          draftId={draft.id}
          onClose={() => setShowScoring(false)}
          onApproved={() => { setShowScoring(false); onApproved() }}
        />
      )}
```

- [ ] **Step 2: Type-check and lint the frontend**

Run: `cd frontend && npx tsc --noEmit && npm run lint`
Expected: no type errors, no lint errors.

- [ ] **Step 3: Run the full frontend test suite**

Run: `cd frontend && npm run test:run`
Expected: PASS — all component and lib tests green.

- [ ] **Step 4: Commit**

```bash
git add frontend/components/DraftEditor.tsx
git commit -m "feat(scoring): require scoring before draft approval in editor"
```

---

## Task 10: Final Verification

- [ ] **Step 1: Run the complete backend suite**

Run: `cd backend && php artisan test`
Expected: all tests PASS.

- [ ] **Step 2: Run the complete frontend suite**

Run: `cd frontend && npm run test:run`
Expected: all tests PASS.

- [ ] **Step 3: Manual smoke test (optional, requires running app)**

Start the app, generate or open a drafting-stage draft, click **Approve & Finalize**, confirm the scoring modal appears with both criteria, that a low score reveals required tags, and that approval succeeds only once all criteria are scored. Refresh and confirm the draft shows as Approved.

- [ ] **Step 4: Confirm clean working tree**

Run: `git status`
Expected: nothing to commit (all tasks committed).

---

## Notes for the Implementer

- **Migrations run automatically in production** via `entrypoint.sh` (`php artisan migrate --force`), so no separate deploy step is needed for the schema changes.
- **Validation uses an explicit `Validator::make` + `after` hook** (not `$request->validate()`) so the low-score-tag requirement can add a precise `scores.{key}.tags` error.
- **The recipe snapshot is copied from the draft** (`lead_persona_id`, `reviewer_persona_ids`, `context_ids`, `model`, `type`, `version`) — this is why Task 4 persists `context_ids`/`model` at generation time.
- **Race safety:** the `approved` status guard plus the unique index on `requirement_draft_id` prevent duplicate scores; the `QueryException` catch translates the unique-violation SQLSTATE (class `23`) into the same clean 422.
- **Generation-endpoint test mocks are strict (Mockery):** because Task 4 makes the controller call `$this->gemini->getModel()` at draft-creation time, every mock that exercises `/generate/brd|stories|spec` must stub `getModel()` — three in `GenerationTest`, plus the `streamBrd` tests in `ChatTest` and `AgenticPersonaTest` (done in Task 4, Step 4) — or they throw `BadMethodCallException`.
- **`lead_persona_id` is a foreign key** to `personas` (enforced under SQLite test config `foreign_key_constraints => true`). Any test that sets it must create a real `Persona` first — hence `DraftScoreTest::makeDraft()` creates one. The existing `RequirementDraftTest::createDraft()` helper does **not** set `lead_persona_id` (it is nullable), so Task 7 needs only to add the scores payload.
- **Capture-only:** scores are stored but never displayed in the UI in v1. Analytics/dashboards are a later iteration.
