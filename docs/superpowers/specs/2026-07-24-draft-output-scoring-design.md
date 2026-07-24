# Draft Output Scoring — Design Spec

**Date:** 2026-07-24  
**Status:** Draft  
**Feature:** User Feedback — Output Scoring for AI-Generated Drafts

---

## 1. Problem

AI-generated drafts (BRD, User Stories, Technical Spec) flow through generation → committee review → synthesis → manual edit → approval, but the system captures **no quality signal** about how good the output was. There is no way to know whether a persona, context set, or model produces better or worse documents over time. The roadmap's continuous-improvement loop (score → aggregate → insight → intervene) has no input signal.

Users approve documents regularly; that moment of human judgment is currently discarded.

---

## 2. What We're Building

A **required scoring step at approval time**. Before a draft can be approved, the reviewer rates it on a two-criterion rubric (each 1–5):

- **Domain Accuracy (Context Grounding)** — how factually accurate the document is in relation to the provided source context, and whether it is free of hallucinated features or assumptions
- **Structural Completeness & Conformance** — how well the output adheres to the required formatting rules, including all mandatory sections (e.g., Executive Summary, User Stories)

When any criterion scores ≤ 2, the reviewer must pick at least one **issue tag** from that criterion's own tag set (structured diagnosis, no free text). The scores, tags, and a frozen **recipe snapshot** (personas, contexts, model, draft type/version) are stored in a new `draft_scores` table. No analytics UI in v1 — capture only.

**Key decisions:**
- Scoring target: per draft (BRD / Stories / Spec)
- Scale: 1–5 per criterion; two criteria; rubric is config-driven
- Timing: required to approve — atomic score-and-approve in one DB transaction
- Qualitative layer: per-criterion issue tag sets, required on low scores (≤ 2)
- Analytics: capture only; benchmarking/dashboards come in a later iteration

---

## 3. Architecture Overview

```
Generate → [Edit / Synthesize] → Approve & Finalize
                                      │
                                      ▼
                              Scoring Modal (2 criteria × 1–5 stars,
                              tags required when a score ≤ 2)
                                      │
                                      ▼
                     POST approve {scores} ──► DB transaction:
                     insert draft_scores row + draft.status = approved
```

- **Backend:** `DraftScore` model + migration, `config/scoring.php` rubric config, extended `RequirementDraftController::approve()`, `ScoringConfigController` (read-only), two new columns on `requirement_drafts` (`context_ids`, `model`)
- **Frontend:** `ScoringModal` component (star rows + tag chips + `InfoTooltip`), opened from `DraftEditor`'s Approve button
- **Data flow:** user scores → server validates (per-criterion, scoped tags) → one transaction persists score with recipe snapshot and approves the draft → score immutable thereafter

---

## 4. Data Model

### 4.1 New Table: `draft_scores`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `requirement_draft_id` | bigint | FK → `requirement_drafts`, **unique**, `cascadeOnDelete()` |
| `user_id` | bigint | FK → `users` — who scored |
| `criterion_scores` | json | Map keyed by criterion key, e.g. `{"domain_accuracy": {"score": 4, "tags": []}, "structural_completeness": {"score": 2, "tags": ["missing_section"]}}` |
| `overall_score` | decimal(2,1) | Mean of criterion scores, stored for cheap future aggregation |
| `recipe_snapshot` | json | Frozen recipe: `{lead_persona_id, reviewer_persona_ids, context_ids, model, draft_type, draft_version}` |
| `created_at` / `updated_at` | timestamps | |

**Rationale:**
- One row per draft (unique index) — the score locks with approval, consistent with the pre-approval state-integrity rules used elsewhere.
- `criterion_scores` as a JSON map keyed by criterion key → the rubric can grow via config without migrations.
- `recipe_snapshot` makes each score self-contained for the later statistical credit-assignment work. `ai_generation_logs` links to features, not drafts, and the contexts used at generation are currently never persisted — so joins cannot reliably reconstruct the recipe. The snapshot avoids that dependency.
- `overall_score` is a derived convenience column for future averaging queries.
- `cascadeOnDelete()` matches the existing `draft_critiques` pattern.

### 4.2 New Columns on `requirement_drafts`

| Column | Type | Notes |
|--------|------|-------|
| `context_ids` | json, nullable | The merged context set (project-linked + request-provided) used at generation. Currently computed in `GenerationController::generate()` but never persisted. |
| `model` | string, nullable | The Gemini model identifier used for the generation, captured at draft creation. |

Both are written at draft creation in `GenerationController::generate()` and copied into the recipe snapshot at scoring time. Nullable so existing rows and non-AI drafts stay valid.

### 4.3 Model Relations

- `DraftScore` → `belongsTo(RequirementDraft)`, `belongsTo(User)`; casts `criterion_scores` and `recipe_snapshot` to array
- `RequirementDraft` → `hasOne(DraftScore::class)` named `score()`

---

## 5. Scoring Config — `config/scoring.php`

The rubric is config-driven, not hardcoded — adding a criterion or tag later requires no migration:

```php
return [
    'low_score_threshold' => 2,

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

The same rubric applies to all three draft types in v1; per-type rubrics are a config-level extension later.

---

## 6. API Contract

### 6.1 `POST /api/features/{feature}/drafts/{draft}/approve` (extended)

Existing guards unchanged: `checkAccess()` (ownership, draft-to-feature consistency), feature not `completed`.

**New payload (required):**

```json
{
  "scores": {
    "domain_accuracy": { "score": 4 },
    "structural_completeness": { "score": 2, "tags": ["missing_section"] }
  }
}
```

**Validation (422, per-criterion field messages):**
- `scores` required; must contain exactly the criterion keys configured in `config/scoring.php` — no missing keys, no unknown keys
- `scores.{key}.score` — required, integer, 1–5
- `scores.{key}.tags` — array; **required with ≥ 1 entry when score ≤ `low_score_threshold`**; must be empty or omitted otherwise; every tag must exist in *that criterion's* configured tag set (scoped validation — tags from another criterion's set are rejected)

**Behavior (single DB transaction):**
1. Reject with 422 if the draft is already `approved` — prevents re-scoring and status regression
2. Build the recipe snapshot from the draft: `lead_persona_id`, `reviewer_persona_ids`, `context_ids`, `model`, `draft_type`, `draft_version`
3. Create the `DraftScore` row (`criterion_scores`, `overall_score` = mean, `recipe_snapshot`, `user_id` = scoring user)
4. `$draft->approve()`
5. Any failure rolls back both — a draft can never be approved without its score

**Response:** unchanged shape — `{"data": $draft->fresh()}`.

### 6.2 `GET /api/scoring-config` (new, authenticated)

Returns the rubric config (criteria, labels, questions, anchors, tag sets, threshold) so the frontend never hardcodes it. Single source of truth; config changes propagate without a frontend deploy.

---

## 7. Frontend

### 7.1 `ScoringModal` component (new)

- Opened from `DraftEditor`'s **Approve & Finalize** button (which no longer calls the API directly)
- Fetches the rubric from `GET /api/scoring-config` on mount (loading state; error state with retry — approval is blocked if the rubric cannot be loaded, since scoring blind is not allowed)
- Per criterion:
  - Label + `InfoTooltip` showing the full question and the 1/5 anchor descriptions
  - 5-star row (click to set)
  - When the selected score ≤ threshold: that criterion's tag chips appear, marked required
- Footer: **Cancel** / **Score & Approve** (primary). Submit disabled until every criterion has a score and all required tags are chosen
- On submit: single POST to the approve endpoint → success triggers the existing `onApproved()` refresh; 422 errors map back to per-criterion display; other errors via `useNotifications`

### 7.2 `DraftEditor` changes

- `handleApprove` opens the modal instead of POSTing directly
- The approved-draft view is unchanged — scores are captured, not displayed (capture-only v1)

---

## 8. Error Handling

| Scenario | Behavior |
|----------|----------|
| Approve without `scores` | 422: per-criterion "score is required" messages |
| Score outside 1–5 or non-integer | 422 validation error |
| Low score (≤ 2) without tags | 422: select at least one issue tag for that criterion |
| Tag from another criterion's set, or unknown tag | 422 validation error |
| Unknown / missing criterion keys | 422 validation error |
| Draft already approved | 422: "Draft has already been approved." (score immutable) |
| Feature finalized | Existing 422 preserved |
| DB failure mid-approve | Transaction rolls back — no score, no approval |
| Scoring-config fetch failure | Modal shows error with retry; approval blocked |

---

## 9. Rate Limiting & Concurrency

- No new throttle needed — approve is a single authenticated action already gated by ownership checks
- The unique index on `requirement_draft_id` plus the already-approved guard prevents double-approval races from creating duplicate scores

---

## 10. Testing

**Backend feature tests:**
- Approve rejected (422) without `scores`, with a missing criterion, with an unknown criterion, with score out of range
- Low score (≤ 2) without tags → 422; with tags from the wrong criterion's set → 422
- Happy path: score row created with the correct criterion map, computed `overall_score`, and full recipe snapshot; draft status becomes `approved`
- Atomicity: forced failure during score creation → draft not approved, no partial rows
- Re-approve attempt → 422, existing score unchanged
- `context_ids` and `model` persisted on the draft at generation time
- `GET /api/scoring-config` returns the configured rubric and requires auth

**Frontend (vitest):**
- Modal renders criteria and tag sets from the config response
- Tag chips appear and are required only when a score ≤ 2
- Submit gating (disabled until valid)
- Successful submit calls the approve endpoint with the correct payload shape

---

## 11. Out of Scope (Future)

- Analytics UI / benchmarking dashboards (consumes this table later)
- Re-scoring or editing scores post-approval
- Per-draft-type rubrics (config structure already supports extension)
- Reprompt integration (separate feature; reprompt instructions become another diagnostic signal alongside tags)
- Multi-scorer support (unique index is per-draft; team review flows can revisit this)
