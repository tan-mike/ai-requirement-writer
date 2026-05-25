# Relational Critique Storage Design

**Goal:** Move expert critiques from a JSON column in `requirement_drafts` to a dedicated `draft_critiques` table to solve concurrency issues and improve data integrity.

**Architecture:** 
- Introduce a `DraftCritique` model and table.
- Refactor `ProcessCritiqueJob` to perform independent inserts/updates.
- Update frontend to consume the new relationship-based data structure.
- Provide a migration path for existing JSON data.

**Tech Stack:** Laravel, MySQL/PostgreSQL, Next.js (TypeScript).

---

## 1. Database Schema

### `draft_critiques` Table
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | Primary key. |
| `requirement_draft_id` | ForeignID | FK to `requirement_drafts`. Cascade on delete. |
| `persona_id` | ForeignID | FK to `personas`. Cascade on delete. |
| `content` | LongText | The markdown content of the critique. |
| `status` | String | `pending`, `completed`, `failed`. |
| `timestamps` | Timestamps | `created_at` and `updated_at`. |

**Constraint:** Unique index on `['requirement_draft_id', 'persona_id']` to ensure one review per persona per draft.

---

## 2. Backend Logic Changes

### Model: `RequirementDraft`
- Remove `critiques` from `$casts` (after verification).
- Add `critiques(): HasMany` relationship to `DraftCritique`.

### Job: `ProcessCritiqueJob`
1. Generate critique via AI.
2. Perform an `updateOrCreate` on the `DraftCritique` table.
3. Count related `DraftCritique` rows for the draft.
4. If `count === count(reviewer_persona_ids)`, update draft status to `refining`.

### Migration Plan
1. **Step 1:** Create `draft_critiques` table.
2. **Step 2:** Data migration script:
   - Loop through all drafts.
   - Decode `critiques` JSON.
   - For each key-value pair, find the Persona by name and create a `DraftCritique` row.
3. **Step 3:** Drop `critiques` column from `requirement_drafts`.

---

## 3. Frontend Logic Changes

### Interface Update
```typescript
interface Critique {
  id: number;
  persona_id: number;
  persona: { name: string };
  content: string;
  status: string;
}

interface Draft {
  // ... existing fields
  critiques: Critique[];
}
```

### Component: `CommitteeNotes.tsx`
- Iterate over the `critiques` array.
- Use `critique.persona.name` for tab labels.
- Use `critique.content` for the Markdown body.

---

## 4. Verification Plan
1. **Automated Tests:**
   - Verify `ProcessCritiqueJob` creates rows in the new table.
   - Verify multiple parallel jobs don't overwrite each other.
   - Verify data migration script preserves existing reviews.
2. **Manual Check:**
   - Run a generation and ensure the "Committee Notes" modal displays feedback correctly.
