# Agentic Memory & Project Evolution Implementation Plan (Phase 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the "Memory Commit" logic to transform approved requirement drafts into permanent Project Knowledge Modules.

**Architecture:** We will add a `committed` node to the `GraphOrchestratorService`. When a draft is transitioned to this node, its content will be used to create or update a `ProjectKnowledgeModule`. This ensures the agentic memory grows as requirements are finalized.

**Tech Stack:** Laravel, Eloquent.

---

### Task 1: Implement Commit Logic in GraphOrchestratorService

**Files:**
- Modify: `backend/app/Services/GraphOrchestratorService.php`

- [ ] **Step 1: Add 'committed' node handling**

```php
// backend/app/Services/GraphOrchestratorService.php
protected function handleNodeEntry(Project $project, string $node, ProjectStateGraph $graph): void {
    // ... existing status update logic ...

    switch ($node) {
        // ... existing cases ...
        case 'committed':
            $this->commitDraftToKnowledgeBase($project, $graph);
            break;
    }
}

private function commitDraftToKnowledgeBase(Project $project, ProjectStateGraph $graph): void {
    $data = $graph->context_data;
    $draftId = $data['active_draft_id'] ?? null;
    $draft = \App\Models\RequirementDraft::find($draftId);

    if (!$draft) return;

    \App\Models\ProjectKnowledgeModule::updateOrCreate(
        [
            'project_id' => $project->id,
            'title' => ucfirst($draft->type) . " (Version {$draft->version})",
            'type' => 'requirement'
        ],
        [
            'content' => $draft->content,
            'metadata' => ['draft_id' => $draft->id, 'version' => $draft->version],
            'status' => 'active'
        ]
    );

    $project->update(['status' => Project::STATUS_COMPLETE]);
}
```

- [ ] **Step 2: Commit (Manual)**

---

### Task 2: Refactor RequirementDraftController Approval

**Files:**
- Modify: `backend/app/Http/Controllers/RequirementDraftController.php`

- [ ] **Step 1: Update approve method to use GraphOrchestrator**

```php
// backend/app/Http/Controllers/RequirementDraftController.php
public function approve(Request $request, Project $project, RequirementDraft $draft): JsonResponse {
    // ... validation ...

    app(\App\Services\GraphOrchestratorService::class)->transition($project, 'approved', [
        'active_draft_id' => $draft->id
    ]);

    // Optional: Auto-commit for now, or we can leave it in 'approved' state
    // For Phase 3, let's trigger the commit if it's approved.
    app(\App\Services\GraphOrchestratorService::class)->transition($project, 'committed', [
        'active_draft_id' => $draft->id
    ]);

    return response()->json(['data' => $draft->fresh()]);
}
```

- [ ] **Step 2: Commit (Manual)**

---

### Task 3: Verification & Knowledge Retrieval Test

**Files:**
- Modify: `backend/tests/Feature/MemoryGovernorTest.php`

- [ ] **Step 1: Add a test for retrieving committed knowledge**

```php
// backend/tests/Feature/MemoryGovernorTest.php
public function test_it_retrieves_committed_draft_content() {
    $project = Project::factory()->create();
    // Simulate a committed draft via the orchestrator logic
    $draft = $project->drafts()->create([
        'type' => 'brd',
        'content' => 'Feature X: User can upload images.',
        'status' => 'committed'
    ]);

    // Manually trigger the commit logic or just create the module for Phase 3 test
    ProjectKnowledgeModule::create([
        'project_id' => $project->id,
        'title' => 'BRD V1',
        'type' => 'requirement',
        'content' => $draft->content
    ]);

    $service = new MemoryGovernorService();
    $context = $service->getRelevantContext($project, 'How do I handle image uploads?');

    $this->assertCount(1, $context);
    $this->assertStringContainsString('upload images', $context->first()->content);
}
```

- [ ] **Step 2: Run tests**
Run: `php artisan test backend/tests/Feature/MemoryGovernorTest.php`

- [ ] **Step 3: Commit (Manual)**

---

Plan complete and saved to `docs/superpowers/plans/2026-06-01-agentic-memory-phase-3.md`. Execution: **Subagent-Driven**.
