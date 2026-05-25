# AI Generation Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Track all AI interactions (prompts, responses, tokens, time) to analyze usage and improve cost-efficiency/effectiveness.

**Architecture:** A central `ai_generation_logs` table stores immutable records of every Gemini API call. The `GeminiGenerationService` is updated to wrap calls with timing and logging logic.

**Tech Stack:** PHP 8.2+, Laravel 11, Gemini PHP SDK, SQLite/MySQL.

---

### Task 1: Create Migration for `ai_generation_logs`

**Files:**
- Create: `backend/database/migrations/2026_05_25_000000_create_ai_generation_logs_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type'); // brd, stories, spec, critique, discovery, synthesis, architecture
            $table->string('model');
            $table->longText('system_prompt')->nullable();
            $table->longText('input_prompt')->nullable();
            $table->longText('output_text')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->string('status')->default('success'); // success, failure
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_logs');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd backend && php artisan migrate`
Expected: `Migration table created successfully` (or similar success message).

- [ ] **Step 3: Commit**

```bash
git add backend/database/migrations/2026_05_25_000000_create_ai_generation_logs_table.php
git commit -m "feat(db): create ai_generation_logs table"
```

---

### Task 2: Create `AiGenerationLog` Model

**Files:**
- Create: `backend/app/Models/AiGenerationLog.php`
- Modify: `backend/app/Models/User.php`
- Modify: `backend/app/Models/Project.php`

- [ ] **Step 1: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'type',
        'model',
        'system_prompt',
        'input_prompt',
        'output_text',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'execution_time_ms',
        'status',
        'error_message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

- [ ] **Step 2: Add relationships to User and Project**

In `backend/app/Models/User.php`:
```php
    public function aiGenerationLogs(): HasMany
    {
        return $this->hasMany(AiGenerationLog::class);
    }
```

In `backend/app/Models/Project.php`:
```php
    public function aiGenerationLogs(): HasMany
    {
        return $this->hasMany(AiGenerationLog::class);
    }
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Models/AiGenerationLog.php backend/app/Models/User.php backend/app/Models/Project.php
git commit -m "feat(models): add AiGenerationLog and relationships"
```

---

### Task 3: Implement Logging Logic in `GeminiGenerationService`

**Files:**
- Modify: `backend/app/Services/GeminiGenerationService.php`

- [ ] **Step 1: Add private log method and project context tracking**

Modify `GeminiGenerationService.php`:
- Add `$currentProject` property.
- Update `forUser` to accept optional project.
- Add `recordLog` private method.

```php
    private ?Project $currentProject = null;

    public function forUser(?User $user, ?Project $project = null): self
    {
        $this->currentUser = $user;
        $this->currentProject = $project;
        // ... rest of method
    }

    private function recordLog(
        string $type,
        ?string $systemPrompt,
        ?string $inputPrompt,
        ?string $outputText,
        ?int $inputTokens,
        ?int $outputTokens,
        int $executionTimeMs,
        string $status = 'success',
        ?string $errorMessage = null,
        array $metadata = []
    ): void {
        \App\Models\AiGenerationLog::create([
            'user_id' => $this->currentUser?->id,
            'project_id' => $this->currentProject?->id,
            'type' => $type,
            'model' => $this->model,
            'system_prompt' => $systemPrompt,
            'input_prompt' => $inputPrompt,
            'output_text' => $outputText,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => ($inputTokens ?? 0) + ($outputTokens ?? 0),
            'execution_time_ms' => $executionTimeMs,
            'status' => $status,
            'error_message' => $errorMessage,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
```

- [ ] **Step 2: Update `generateCritique` to log**

```php
    public function generateCritique(string $systemPrompt, string $draftContent, string $draftType = 'document'): string
    {
        $startTime = microtime(true);
        // ... existing typeLabel and prompt logic
        
        try {
            $response = $this->getClient()->generativeModel(model: $this->model)
                ->withSystemInstruction(Content::parse($systemPrompt))
                ->generateContent($prompt);

            $text = $this->safeExtractText($response);
            $usage = $response->usageMetadata;

            $this->recordLog(
                type: 'critique',
                systemPrompt: $systemPrompt,
                inputPrompt: $prompt,
                outputText: $text,
                inputTokens: $usage->promptTokenCount,
                outputTokens: $usage->candidatesTokenCount,
                executionTimeMs: (int)((microtime(true) - $startTime) * 1000),
                metadata: ['draft_type' => $draftType]
            );

            return $text;
        } catch (\Exception $e) {
            $this->recordLog(
                type: 'critique',
                systemPrompt: $systemPrompt,
                inputPrompt: $prompt,
                outputText: null,
                inputTokens: null,
                outputTokens: null,
                executionTimeMs: (int)((microtime(true) - $startTime) * 1000),
                status: 'failure',
                errorMessage: $e->getMessage()
            );
            throw $e;
        }
    }
```

- [ ] **Step 3: Update `streamGeneration` to capture and log**

Need to buffer output and extract tokens from final response chunk.

```php
    private function streamGeneration(string $systemPrompt, string $userPrompt, callable $onChunk, string $logType = 'generation'): void
    {
        $startTime = microtime(true);
        $fullOutput = '';
        $inputTokens = null;
        $outputTokens = null;

        try {
            $stream = $this->getClient()->generativeModel(model: $this->model)
                ->withSystemInstruction(Content::parse($systemPrompt))
                ->streamGenerateContent($userPrompt);

            // ... heading logic remains
            foreach ($stream as $response) {
                $text = $this->safeExtractText($response);
                if ($text !== '') {
                    $fullOutput .= $text;
                    // ... chunk processing
                }
                
                // Extract usage from last chunk if available
                if ($response->usageMetadata) {
                    $inputTokens = $response->usageMetadata->promptTokenCount;
                    $outputTokens = $response->usageMetadata->candidatesTokenCount;
                }
            }

            $this->recordLog(
                type: $logType,
                systemPrompt: $systemPrompt,
                inputPrompt: $userPrompt,
                outputText: $fullOutput,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                executionTimeMs: (int)((microtime(true) - $startTime) * 1000)
            );
        } catch (\Exception $e) {
            $this->recordLog(
                type: $logType,
                systemPrompt: $systemPrompt,
                inputPrompt: $userPrompt,
                outputText: $fullOutput,
                inputTokens: null,
                outputTokens: null,
                executionTimeMs: (int)((microtime(true) - $startTime) * 1000),
                status: 'failure',
                errorMessage: $e->getMessage()
            );
            throw $e;
        }
    }
```

- [ ] **Step 4: Propagate `logType` through streaming methods**

Update `streamBrd`, `streamStories`, `streamSpec`, `streamSynthesis`, `streamDiscoveryChat` to pass their respective type to `streamGeneration`.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/GeminiGenerationService.php
git commit -m "feat(service): integrate logging into GeminiGenerationService"
```

---

### Task 4: Verification and Tests

**Files:**
- Create: `backend/tests/Feature/AiGenerationLoggingTest.php`

- [ ] **Step 1: Write integration test**

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\AiGenerationLog;
use App\Services\GeminiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGenerationLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_critique_generation()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        
        // Mock Gemini facade if necessary or use a real test if key is present
        // For plan simplicity, assume service works and check DB after call
        $service = app(GeminiGenerationService::class)->forUser($user, $project);
        
        try {
            $service->generateCritique("You are a reviewer", "Some draft content", "brd");
        } catch (\Exception $e) {
            // Even if it fails (no API key), it should log the failure
        }

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'type' => 'critique',
        ]);
    }
}
```

- [ ] **Step 2: Run tests**

Run: `cd backend && php artisan test tests/Feature/AiGenerationLoggingTest.php`
Expected: `OK (1 test, 1 assertion)` (or similar).

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/AiGenerationLoggingTest.php
git commit -m "test: add ai generation logging integration test"
```
