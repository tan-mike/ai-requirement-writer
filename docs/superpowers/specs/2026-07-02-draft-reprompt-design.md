# Draft Reprompt Feature — Design Spec

**Date:** 2026-07-02  
**Status:** Draft  
**Feature:** AI Draft Reprompt (Refine with Feedback)

---

## 1. Problem

After AI generates a requirement document (BRD, Stories, or Spec), the user can manually edit or approve — but cannot ask the AI to *refine* the draft with specific feedback. The only regeneration option is "Regenerate from scratch" (available only after approval), which discards everything and provides no guidance to the AI.

This leaves a gap: users with drafts that are *close but not right* must either tediously hand-edit or lose all progress.

---

## 2. What We're Building

A **reprompt** feature that lets users provide targeted instructions to the AI, which then refines the current draft in place. The refined version streams into a side-by-side preview panel, and the user explicitly accepts or discards it.

**Key decisions:**
- Refinement mode: edit pass on current draft (not fresh generation)
- Available only in pre-approval state (`drafting` status)
- Two UI modes: inline text input (quick one-shot) and chat thread (iterative)
- Side-by-side preview with accept/keep controls
- Instructions accumulate across reprompts, capped at 5

---

## 3. Architecture Overview

```
Generate → [Edit / Reprompt] → Approve → Lock
                ↑        ↓
                └────────┘  (side-by-side accept/keep)
```

**Components:**
- **Backend:** `DraftReprompt` model, `RepromptController` with two endpoints, `streamRefine()` in `GeminiGenerationService`
- **Frontend:** Reprompt panel (inline + chat tabs), side-by-side preview with accept/keep action bar
- **Data flow:** User instruction → stored in `draft_reprompts` → prompt assembled with current draft + accumulated instructions → streamed to preview panel → user accepts (server copies server-held content to draft) or discards

---

## 4. Data Model

### 4.1 New Table: `draft_reprompts`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `draft_id` | bigint | FK → `requirement_drafts`, `cascadeOnDelete()` |
| `user_id` | bigint | FK → `users` |
| `instruction` | text | User's reprompt message (max 4000 chars) |
| `preview_content` | longText | Server-accumulated stream output (nullable until stream completes) |
| `sequence` | int | Order within this reprompt thread |
| `created_at` | timestamp | |

**Rationale:**
- `preview_content` stores the server-side streamed result so the accept endpoint copies from the server, not the client. This maintains the integrity pattern used by `GenerationController`.
- `mode` (inline/chat) is intentionally excluded — it's UI state, not data. YAGNI.
- `cascadeOnDelete()` matches the existing `draft_critiques` pattern.

### 4.2 New Column on `requirement_drafts`

| Column | Type | Notes |
|--------|------|-------|
| `reprompt_locked_at` | timestamp | Nullable. Set when a reprompt stream starts, cleared when it ends or is accepted. Prevents concurrent reprompts. |

### 4.3 Model Relations

`RequirementDraft` gains:
- `reprompts()` → `hasMany(DraftReprompt::class)`
- `repromptCount()` → derived from `reprompts()->count()`

---

## 5. API Contract

### 5.1 `POST /api/features/{feature}/drafts/{draft}/reprompt`

**Authorization:** Same `checkAccess()` pattern as `RequirementDraftController` — validates project ownership, draft-to-feature consistency, and per-user scoping.

**Guards:**
- Draft status must be `drafting` → 422 otherwise
- `reprompt_locked_at` must be null → 409 Conflict if another reprompt is active
- `reprompts()->count() >= 5` → 422 with message
- `instruction` validated: `['required', 'string', 'max:4000']`

**Payload:**
```json
{
  "instruction": "Make section 3 more specific with acceptance criteria"
}
```

**Behavior:**
1. Acquire lock: set `reprompt_locked_at = now()`
2. Store instruction in `draft_reprompts` with next sequence number
3. Fetch all previous instructions for prompt assembly
4. Call `streamRefine()` — streams SSE chunks to client
5. Accumulate streamed content server-side
6. On completion: store accumulated content in `preview_content` on the reprompt record, clear `reprompt_locked_at`
7. On failure/disconnect: clear `reprompt_locked_at`, discard partial `preview_content`

**Response:** SSE stream. Each chunk: `data: {"text": "..."}`. Final event: `data: {"done": true, "reprompt_id": 42}`.

**Response metadata (in final SSE event):**
```json
{
  "done": true,
  "reprompt_id": 42,
  "reprompt_count": 3,
  "cap": 5
}
```

### 5.2 `POST /api/features/{feature}/drafts/{draft}/reprompt/accept`

**Authorization:** Same `checkAccess()`.

**Payload:**
```json
{
  "reprompt_id": 42
}
```

**Behavior:**
1. Look up `DraftReprompt` by ID, verify it belongs to this draft
2. Verify `preview_content` is not null (stream completed successfully)
3. Copy `preview_content` → `draft.content`
4. Status stays `drafting` — user can reprompt again or approve

**Does NOT accept arbitrary content from the client.** The server is the source of truth for what was streamed.

### 5.3 `GET /api/features/{feature}/drafts/{draft}/reprompts`

Returns the list of reprompt instructions and their status (pending preview, accepted, discarded). Used by the chat thread UI to render history.

---

## 6. Backend Service: `streamRefine()`

New method on `GeminiGenerationService`:

```php
public function streamRefine(
    string $systemPrompt,
    string $currentDraftContent,
    array $previousInstructions,  // [{instruction, sequence}, ...]
    string $latestInstruction,
    string $draftType,
    callable $onChunk,
    ?RequirementDraft $draft = null
): void
```

### 6.1 Prompt Construction

```
SYSTEM:
{persona system prompt}

"You are refining an existing {draft type}. The user has provided feedback 
instructions. Rewrite the document incorporating all feedback while preserving 
structure and detail unless explicitly asked to change it. Output the complete 
rewritten document."

USER:
### Current Document:
{currentDraftContent}

### Previous Refinement Instructions (for context):
{formatted previous instructions, or "None"}

### Latest Instruction:
{latestInstruction}

Rewrite the full document incorporating the above instructions.
```

### 6.2 Context Hierarchy

The method passes the `$draft` object through to `streamGeneration()`, which activates the existing context hierarchy:
- Feature intent (title + description) — from draft
- Company context — universal
- Module context — project-specific
- RAG project memory — relevant historical data

Additionally, `streamRefine()` includes the original intake fields and linked project contexts in the prompt to prevent intent drift. These are fetched from the feature's intake form and project contexts, same as the initial generation.

### 6.3 Connection Abort Handling

The streaming callback includes a `connection_aborted()` check. If the client disconnects mid-stream, the Gemini call is terminated and the partial result is discarded (not stored in `preview_content`).

---

## 7. Frontend UI

### 7.1 Reprompt Panel

Appears below the DraftEditor textarea, only when draft status is `drafting`.

**Mode toggle:** Two tabs:
- **"Quick Refine"** — Single textarea + "Refine" button. For quick one-shot instructions.
- **"Chat"** — Message thread showing previous instructions (user messages) and a summary of each refinement result. Input at bottom. For iterative refinement.

**Cap indicator:**
- After 3+ reprompts: subtle warning — *"3 of 5 refinements used"*
- At cap (5): panel disabled — *"Maximum refinements reached. Please approve or manually edit."*

### 7.2 Side-by-Side Preview

Activates when a reprompt is submitted:

- Layout splits into two columns:
  - **Left:** Current draft content (read-only during streaming, labeled "Current")
  - **Right:** Streaming refined content (labeled "Refined Preview")

- **Action bar** appears below both panels after streaming completes:
  - **"Accept Refinement"** (primary) — calls accept endpoint with `reprompt_id`
  - **"Keep Current"** (secondary) — discards preview, returns to normal editor

- **During streaming:** "Cancel" button available to stop the stream

### 7.3 Chat Thread Rendering

The chat tab renders previous reprompt instructions as user messages. After each accepted refinement, a brief indicator shows "Refinement applied" (no need to show the full previous output — the current draft *is* the result).

---

## 8. Error Handling

| Scenario | Behavior |
|----------|----------|
| Draft already approved | 422: "Draft has been approved and can no longer be refined." |
| Reprompt cap reached | 422: "Maximum of 5 refinements reached." |
| Another reprompt active | 409: "A refinement is already in progress." |
| Empty instruction | Client-side: button disabled. Server-side: 422 validation error. |
| Instruction > 4000 chars | Server-side: 422 validation error. |
| Gemini API failure | Stream errors out, `reprompt_locked_at` cleared, preview discarded, draft untouched. Error message shown. |
| Client disconnects mid-stream | Server detects via `connection_aborted()`, terminates Gemini stream, clears lock, discards partial result. |
| Accept with no preview_content | 422: "No refinement result available." |

---

## 9. Rate Limiting

- **Per-user throttle:** `throttle:5,10` middleware on the reprompt endpoint (5 requests per 10 minutes). Prevents rapid-fire API calls that could exhaust Gemini quota.
- **Concurrent lock:** `reprompt_locked_at` on the draft prevents two simultaneous reprompts on the same draft.
- **Global cap:** 5 reprompts per draft is the hard ceiling.

---

## 10. Future Considerations (Out of Scope for v1)

- **Instruction summarization:** When users approach the cap, add a summarization step that compresses accumulated instructions into a compact summary before building the prompt. Data model supports this without changes.
- **Post-approval reprompt:** Allow reprompting approved drafts with automatic status regression. Requires additional state machine logic.
- **Diff view:** Show a highlighted diff between current and refined content instead of side-by-side.
