# Relational Critique Storage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor critique storage from a JSON column to a dedicated `draft_critiques` table for better concurrency and data integrity.

**Architecture:** 
- Create `DraftCritique` model and table.
- Refactor `ProcessCritiqueJob` to update the new table and check for draft completion.
- Update `RequirementDraft` model to include the `critiques` relationship.
- Refactor frontend to use the new structured data.
- Migrate existing JSON data to the new table.

**Tech Stack:** Laravel (Eloquent, Migrations), Next.js (TypeScript).

---

### Task 1: Backend - Model, Migration, and Data Migration

**Files:**
- Create: `backend/app/Models/DraftCritique.php`
- Create: `backend/database/migrations/YYYY_MM_DD_create_draft_critiques_table.php` (use current date)
- Modify: `backend/app/Models/RequirementDraft.php`

- [ ] **Step 1: Create DraftCritique model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftCritique extends Model
{
    protected $fillable = [
        'requirement_draft_id',
        'persona_id',
        'content',
        'status'
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(RequirementDraft::class, 'requirement_draft_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
```

- [ ] **Step 2: Create migration for draft_critiques table**

```bash
php artisan make:migration create_draft_critiques_table
```

Edit the migration:
```php
public function up(): void
{
    Schema::create('draft_critiques', function (Blueprint $table) {
        $table->id();
        $table->foreignId('requirement_draft_id')->constrained()->cascadeOnDelete();
        $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
        $table->longText('content')->nullable();
        $table->string('status')->default('pending'); // pending, completed, failed
        $table->timestamps();
        
        $table->unique(['requirement_draft_id', 'persona_id']);
    });
}
```

- [ ] **Step 3: Add relationship to RequirementDraft model**

```php
// backend/app/Models/RequirementDraft.php

public function critiques(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(DraftCritique::class, 'requirement_draft_id');
}
```

- [ ] **Step 4: Create and run data migration script**

Create a temporary Artisan command or run via Tinker to move data:
```php
$drafts = App\Models\RequirementDraft::whereNotNull('critiques')->get();
foreach ($drafts as $draft) {
    foreach ($draft->critiques as $roleName => $content) {
        $persona = App\Models\Persona::where('name', $roleName)->first();
        if ($persona) {
            App\Models\DraftCritique::updateOrCreate(
                ['requirement_draft_id' => $draft->id, 'persona_id' => $persona->id],
                ['content' => $content, 'status' => 'completed']
            );
        }
    }
}
```

- [ ] **Step 5: Run tests and commit**

```bash
php artisan migrate
# ... run data migration ...
git add .
git commit -m "feat(backend): add draft_critiques table and migrate data"
```

---

### Task 2: Backend - Refactor ProcessCritiqueJob

**Files:**
- Modify: `backend/app/Jobs/ProcessCritiqueJob.php`

- [ ] **Step 1: Refactor handle method to use DraftCritique model**

Replace the JSON update logic with:
```php
// backend/app/Jobs/ProcessCritiqueJob.php

public function handle(GeminiGenerationService $gemini): void
{
    set_time_limit(0);
    $gemini->forUser($this->draft->project->user);

    $critiqueContent = $gemini->generateCritique(
        $this->reviewer->system_prompt,
        $this->draft->content,
        $this->draft->type
    );

    // Save to the new table
    \App\Models\DraftCritique::updateOrCreate(
        ['requirement_draft_id' => $this->draft->id, 'persona_id' => $this->reviewer->id],
        ['content' => $critiqueContent, 'status' => 'completed']
    );

    // Check completion
    $totalExpected = count($this->draft->reviewer_persona_ids ?? []);
    $completedCount = $this->draft->critiques()->where('status', 'completed')->count();

    if ($completedCount === $totalExpected) {
        $this->draft->update(['status' => 'refining']);
    }
}
```

- [ ] **Step 2: Run verification tests and commit**

```bash
git add backend/app/Jobs/ProcessCritiqueJob.php
git commit -m "refactor(backend): use draft_critiques table in ProcessCritiqueJob"
```

---

### Task 3: Backend - API and Serialization

**Files:**
- Modify: `backend/app/Http/Controllers/RequirementDraftController.php`
- Modify: `backend/app/Http/Controllers/GenerationController.php`

- [ ] **Step 1: Eager load critiques in controllers**

Update `RequirementDraftController@index` and `show` to include `critiques.persona`.
Update `GenerationController@synthesize` to fetch critiques from the relationship.

```php
// backend/app/Http/Controllers/GenerationController.php

// In synthesize method:
$critiques = $draft->critiques()->with('persona')->get()
    ->pluck('content', 'persona.name')
    ->toArray();
```

- [ ] **Step 2: Commit API changes**

```bash
git commit -m "feat(backend): update API to serve relational critiques"
```

---

### Task 4: Frontend - Component and Interface Refactor

**Files:**
- Modify: `frontend/app/(dashboard)/projects/[id]/generate/page.tsx`
- Modify: `frontend/components/CommitteeNotes.tsx`

- [ ] **Step 1: Update Frontend Interfaces**

```typescript
// frontend/app/(dashboard)/projects/[id]/generate/page.tsx

interface Critique {
  id: number
  persona: { name: string }
  content: string
  status: string
}

interface Draft {
  // ... existing fields
  critiques: Critique[] // Change from Record<string, string>
}
```

- [ ] **Step 2: Refactor CommitteeNotes component**

Update props and logic to handle the array of objects.
```typescript
// frontend/components/CommitteeNotes.tsx

interface CommitteeNotesProps {
  critiques: {
    id: number
    persona: { name: string }
    content: string
    status: string
  }[]
}

// Logic update:
const roles = critiques.map(c => c.persona.name)
const [activeTab, setActiveTab] = useState(roles[0])

// Render update:
<ReactMarkdown>{critiques.find(c => c.persona.name === activeTab)?.content || ''}</ReactMarkdown>
```

- [ ] **Step 3: Verify UI and commit**

```bash
git commit -m "feat(frontend): refactor CommitteeNotes to use relational data structure"
```

---

### Task 5: Final Verification and Cleanup

- [ ] **Step 1: Perform full generation cycle**
- [ ] **Step 2: Remove legacy JSON column from RequirementDraft model and DB**

Create migration to drop `critiques` from `requirement_drafts`.
Update `RequirementDraft.php` to remove it from `$fillable`, `$casts`, and `$attributes`.

- [ ] **Step 3: Commit final cleanup**

```bash
git commit -m "chore: drop legacy critiques JSON column"
```
