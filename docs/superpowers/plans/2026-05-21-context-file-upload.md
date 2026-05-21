# Context File Upload Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow users to upload a context file (Markdown, JSON, XML, TXT) during project creation to be used directly as the `architecture_summary`.

**Architecture:** Use Laravel's file upload system to store the context file temporarily, then process it asynchronously in a background job that updates the project's architecture summary and deletes the file.

**Tech Stack:** Laravel, Next.js, TypeScript

---

### Task 1: Backend - ProcessContextFileJob and Tests

**Files:**
- Create: `backend/app/Jobs/ProcessContextFileJob.php`
- Create: `backend/tests/Feature/ContextFileUploadTest.php`

- [ ] **Step 1: Write the feature test for context file processing**

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Jobs\ProcessContextFileJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_file_is_processed_correctly()
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'processing']);
        
        $content = "# Project Context\nThis is a test context.";
        $file = UploadedFile::fake()->createWithContent('context.md', $content);
        $path = $file->store('temp_context');

        $job = new ProcessContextFileJob($project, $path);
        $job->handle();

        $project->refresh();
        $this->assertEquals($content, $project->architecture_summary);
        $this->assertEquals('ready', $project->status);
        Storage::disk('local')->assertMissing($path);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/ContextFileUploadTest.php`
Expected: FAIL (Class App\Jobs\ProcessContextFileJob not found)

- [ ] **Step 3: Create the ProcessContextFileJob**

```php
<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessContextFileJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $filePath
    ) {}

    public function handle(): void
    {
        if (!Storage::exists($this->filePath)) {
            \Illuminate\Support\Facades\Log::error("Context file not found at: {$this->filePath}");
            $this->project->update(['status' => 'error']);
            return;
        }

        $content = Storage::get($this->filePath);
        
        $this->project->update([
            'architecture_summary' => $content,
            'status' => 'ready',
        ]);

        Storage::delete($this->filePath);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/ContextFileUploadTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Jobs/ProcessContextFileJob.php backend/tests/Feature/ContextFileUploadTest.php
git commit -m "feat(backend): add ProcessContextFileJob and tests"
```

---

### Task 2: Backend - Update Project Creation to Handle Context Files

**Files:**
- Modify: `backend/app/Http/Requests/StoreProjectRequest.php`
- Modify: `backend/app/Http/Controllers/ProjectController.php`
- Modify: `backend/tests/Feature/ContextFileUploadTest.php`

- [ ] **Step 1: Add project creation with file test case**

```php
    public function test_user_can_create_project_with_context_file()
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'File Project',
            'type' => 'webapp',
            'context_file' => UploadedFile::fake()->create('context.md', 100),
        ]);

        $response->assertStatus(201);
        $project = Project::first();
        $this->assertEquals('File Project', $project->name);
        
        Queue::assertPushed(ProcessContextFileJob::class, function ($job) use ($project) {
            return $job->project->id === $project->id;
        });
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/ContextFileUploadTest.php`
Expected: FAIL (Unprocessable Content / Validation failed)

- [ ] **Step 3: Update StoreProjectRequest validation**

```php
// backend/app/Http/Requests/StoreProjectRequest.php

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'repository_url' => ['nullable', 'url', 'required_without_all:repository_path,context_file'],
            'repository_path' => ['nullable', 'string', 'required_without_all:repository_url,context_file'],
            'context_file' => ['nullable', 'file', 'max:2048', 'mimes:txt,json,xml,md', 'required_without_all:repository_url,repository_path'],
            'template_id' => ['nullable', 'exists:templates,id'],
        ];
    }
```

- [ ] **Step 4: Update ProjectController store method**

```php
// backend/app/Http/Controllers/ProjectController.php

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $contextFile = $request->file('context_file');
        
        // Remove file from data to prevent direct saving to DB if it was added to fillable (unlikely but safe)
        unset($data['context_file']);

        $project = $request->user()->projects()->create($data);

        if ($contextFile) {
            $path = $contextFile->store('temp_context');
            \App\Jobs\ProcessContextFileJob::dispatch($project, $path);
        } elseif ($project->repository_url || $project->repository_path) {
            \App\Jobs\ProcessRepositoryJob::dispatch($project);
        }

        return response()->json(['data' => $project], 201);
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `cd backend && php artisan test tests/Feature/ContextFileUploadTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Requests/StoreProjectRequest.php backend/app/Http/Controllers/ProjectController.php backend/tests/Feature/ContextFileUploadTest.php
git commit -m "feat(backend): support context_file upload in project creation"
```

---

### Task 3: Frontend - Update Project Creation Form

**Files:**
- Modify: `frontend/app/(dashboard)/projects/new/page.tsx`
- Modify: `frontend/lib/api.ts`

- [ ] **Step 1: Update API library to handle FormData**

Check if `frontend/lib/api.ts` `createProject` already handles FormData or needs adjustment.

```typescript
// frontend/lib/api.ts
// Ensure createProject can accept FormData or objects that will be converted to FormData if a file is present.
```

- [ ] **Step 2: Update New Project Form UI**

- Add a toggle or separate section for "Repository" vs "Context File".
- Add file input for `context_file`.
- Handle state and submission.

- [ ] **Step 3: Manually verify the form submission**

- Create a project via the UI using a `.md` file.
- Verify it redirects to the project page and eventually shows the context.

- [ ] **Step 4: Commit**

```bash
git add frontend/app/\(dashboard\)/projects/new/page.tsx frontend/lib/api.ts
git commit -m "feat(frontend): add context file upload to new project form"
```
