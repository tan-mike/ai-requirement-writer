# Project Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically mark projects as 'complete' when all requirements are approved, and allow manual completion.

**Architecture:** Update the `RequirementDraftController::approve` method to trigger a project status check. Add a manual completion endpoint in `ProjectController`.

**Tech Stack:** Laravel, Next.js, Tailwind CSS.

---

### Task 1: Backend - Define STATUS_COMPLETE and check for completion

**Files:**
- Modify: `backend/app/Models/Project.php`
- Modify: `backend/app/Http/Controllers/RequirementDraftController.php`

- [ ] **Step 1: Add STATUS_COMPLETE constant to Project model**

```php
// backend/app/Models/Project.php

    const STATUS_DRAFT = 'draft';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_FAILED = 'error';
    const STATUS_COMPLETE = 'complete'; // Add this
```

- [ ] **Step 2: Implement checkProjectCompletion method in RequirementDraftController**

```php
// backend/app/Http/Controllers/RequirementDraftController.php

    public function approve(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        // ... existing auth checks ...

        $draft->approve();

        $this->checkProjectCompletion($project); // Add this

        return response()->json(['data' => $draft->fresh()]);
    }

    private function checkProjectCompletion(Project $project): void
    {
        $requiredTypes = ['brd', 'stories', 'spec'];
        
        $allApproved = collect($requiredTypes)->every(function ($type) use ($project) {
            return $project->drafts()->where('type', $type)->where('status', 'approved')->exists();
        });

        if ($allApproved) {
            $project->update(['status' => Project::STATUS_COMPLETE]);
        }
    }
```

- [ ] **Step 3: Commit backend changes**

```bash
git add backend/app/Models/Project.php backend/app/Http/Controllers/RequirementDraftController.php
git commit -m "feat(backend): add automatic project completion logic"
```

### Task 2: Backend - Add manual complete endpoint

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/ProjectController.php`

- [ ] **Step 1: Add manual complete route**

```php
// backend/routes/api.php

    Route::apiResource('projects', ProjectController::class)->except(['update']);
    Route::post('/projects/{project}/complete', [ProjectController::class, 'complete']); // Add this
```

- [ ] **Step 2: Implement complete method in ProjectController**

```php
// backend/app/Http/Controllers/ProjectController.php

    public function complete(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $project->update(['status' => Project::STATUS_COMPLETE]);

        return response()->json(['data' => $project]);
    }
```

- [ ] **Step 3: Commit manual completion endpoint**

```bash
git add backend/routes/api.php backend/app/Http/Controllers/ProjectController.php
git commit -m "feat(backend): add manual project completion endpoint"
```

### Task 3: Frontend - Add "Complete Project" button and status UI

**Files:**
- Modify: `frontend/app/(dashboard)/projects/[id]/generate/page.tsx`

- [ ] **Step 1: Add project status to Project interface and state**

```typescript
// frontend/app/(dashboard)/projects/[id]/generate/page.tsx

interface ProjectResponse {
  data: {
    id: number
    name: string
    status: string // Add this
    lead_persona_id: number
    lead_persona?: { id: number; name: string }
  }
}
```

- [ ] **Step 2: Add handleComplete function**

```typescript
// frontend/app/(dashboard)/projects/[id]/generate/page.tsx

  async function handleComplete() {
    try {
      await apiClient.post(`/projects/${id}/complete`)
      loadData()
    } catch (err) {
      setError('Failed to mark project as complete')
    }
  }
```

- [ ] **Step 3: Render the "Complete Project" button**

Add it at the top or bottom of the generation steps. If the project is already complete, show a "Project Completed" badge.

```tsx
// frontend/app/(dashboard)/projects/[id]/generate/page.tsx

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-4xl font-extrabold tracking-tight text-foreground">Generate Requirements</h1>
          <p className="text-muted-foreground mt-2 text-lg">Build high-quality documentation with AI experts.</p>
        </div>

        {project?.status === 'complete' ? (
          <div className="bg-emerald-100 text-emerald-700 px-6 py-3 rounded-xl font-bold border-2 border-emerald-200 flex items-center gap-2 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Project Completed
          </div>
        ) : isApproved('brd') && (
          <button
            onClick={handleComplete}
            className="btn-primary flex items-center gap-2"
          >
            Mark as Completed
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </button>
        )}
      </div>
```

- [ ] **Step 4: Commit frontend changes**

```bash
git add frontend/app/(dashboard)/projects/[id]/generate/page.tsx
git commit -m "feat(frontend): add project completion UI and logic"
```
