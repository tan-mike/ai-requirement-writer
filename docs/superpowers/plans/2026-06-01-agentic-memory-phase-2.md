# Agentic Memory & Project Evolution Implementation Plan (Phase 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the linear critique process into a flexible, state-graph orchestration system that supports branching and persistence.

**Architecture:** We will introduce a `ProjectStateGraph` model to track the "Blackboard" state and a `GraphOrchestratorService` to handle transitions. `ProcessCritiqueJob` will be refactored into a "Review Node" within this graph.

**Tech Stack:** Laravel, Eloquent (JSON for state persistence), Google Gemini API.

---

### Task 1: ProjectStateGraph Infrastructure

**Files:**
- Create: `backend/database/migrations/2026_06_01_000002_create_project_state_graphs_table.php`
- Create: `backend/app/Models/ProjectStateGraph.php`
- Modify: `backend/app/Models/Project.php`

- [ ] **Step 1: Create the migration**

```php
// backend/database/migrations/2026_06_01_000002_create_project_state_graphs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('project_state_graphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_node'); // drafting, reviewing, refining, approved, committed
            $table->json('context_data'); // The Blackboard (shared state)
            $table->boolean('is_paused')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('project_state_graphs');
    }
};
```

- [ ] **Step 2: Run the migration**
Run: `php artisan migrate`

- [ ] **Step 3: Create the ProjectStateGraph model**

```php
// backend/app/Models/ProjectStateGraph.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStateGraph extends Model {
    protected $fillable = ['project_id', 'current_node', 'context_data', 'is_paused'];
    protected $casts = ['context_data' => 'array', 'is_paused' => 'boolean'];

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }
}
```

- [ ] **Step 4: Update Project model**

```php
// backend/app/Models/Project.php
public function stateGraph(): HasOne {
    return $this->hasOne(ProjectStateGraph::class);
}
```

- [ ] **Step 5: Commit (Manual)**
(Skip git command as per user request)

---

### Task 2: GraphOrchestratorService & Node Logic

**Files:**
- Create: `backend/app/Services/GraphOrchestratorService.php`
- Modify: `backend/app/Jobs/ProcessCritiqueJob.php`

- [ ] **Step 1: Create the GraphOrchestratorService**

```php
// backend/app/Services/GraphOrchestratorService.php
namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStateGraph;

class GraphOrchestratorService {
    public function transition(Project $project, string $targetNode, array $data = []): void {
        $graph = $project->stateGraph()->firstOrCreate(
            ['project_id' => $project->id],
            ['current_node' => 'idle', 'context_data' => []]
        );

        $graph->update([
            'current_node' => $targetNode,
            'context_data' => array_merge($graph->context_data, $data)
        ]);

        $this->handleNodeEntry($project, $targetNode, $graph);
    }

    protected function handleNodeEntry(Project $project, string $node, ProjectStateGraph $graph): void {
        switch ($node) {
            case 'reviewing':
                // Logic to trigger critiques if not already done
                break;
            case 'refining':
                // Logic to signal user or auto-refine
                break;
        }
    }
}
```

- [ ] **Step 2: Refactor ProcessCritiqueJob to call Orchestrator**

```php
// backend/app/Jobs/ProcessCritiqueJob.php:49 (in handle method)
if ($completedCount === $totalExpected) {
    app(GraphOrchestratorService::class)->transition($this->draft->project, 'refining', [
        'last_review_completed_at' => now()->toDateTimeString(),
        'active_draft_id' => $this->draft->id
    ]);
}
```

- [ ] **Step 3: Commit (Manual)**

---

### Task 3: Refactor GenerationController to use Graph

**Files:**
- Modify: `backend/app/Http/Controllers/GenerationController.php`

- [ ] **Step 1: Update controller to initialize graph state**

```php
// backend/app/Http/Controllers/GenerationController.php:116
if ($reviewers->isNotEmpty()) {
    app(GraphOrchestratorService::class)->transition($project, 'reviewing', [
        'active_draft_id' => $draft->id,
        'reviewer_ids' => $reviewers->pluck('id')->toArray()
    ]);
    
    foreach ($reviewers as $reviewer) {
        ProcessCritiqueJob::dispatch($draft, $reviewer);
    }
    // ...
}
```

- [ ] **Step 2: Commit (Manual)**

---

Plan complete and saved to `docs/superpowers/plans/2026-06-01-agentic-memory-phase-2.md`. Execution: **Subagent-Driven**.
