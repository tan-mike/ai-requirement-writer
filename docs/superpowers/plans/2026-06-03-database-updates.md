# Database Curation & Shared Requirements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend database schema to support curated projects, user-specific requirement drafts, and system settings.

**Architecture:** Laravel migrations for schema updates and Eloquent models for data access. Updates unique constraints on `requirement_drafts` to allow per-user versioning.

**Tech Stack:** PHP, Laravel (Eloquent, Migrations), SQLite/MySQL.

---

### Task 1: Project Model & Schema Update

**Files:**
- Create: `backend/database/migrations/2026_06_03_000001_add_description_and_category_to_projects_table.php`
- Modify: `backend/app/Models/Project.php`

- [ ] **Step 1: Create migration to add description and category**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('category')->nullable()->after('is_curated');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['description', 'category']);
        });
    }
};
```

- [ ] **Step 2: Update Project model fillable**

Modify `backend/app/Models/Project.php` to include `description` and `category` in `$fillable`.

- [ ] **Step 3: Commit**

```bash
git add backend/database/migrations/2026_06_03_000001_add_description_and_category_to_projects_table.php backend/app/Models/Project.php
git commit -m "feat(db): add description and category to projects"
```

### Task 2: RequirementDraft Model & Schema Update

**Files:**
- Create: `backend/database/migrations/2026_06_03_000002_add_user_id_to_requirement_drafts_table.php`
- Modify: `backend/app/Models/RequirementDraft.php`

- [ ] **Step 1: Create migration for user_id and unique constraint update**

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
            $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['project_id', 'type', 'version']);
            $table->unique(['project_id', 'type', 'version', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'type', 'version', 'user_id']);
            $table->unique(['project_id', 'type', 'version']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
```

- [ ] **Step 2: Update RequirementDraft model**

Modify `backend/app/Models/RequirementDraft.php` to include `user_id` in `$fillable` and add `user()` relationship.

- [ ] **Step 3: Commit**

```bash
git add backend/database/migrations/2026_06_03_000002_add_user_id_to_requirement_drafts_table.php backend/app/Models/RequirementDraft.php
git commit -m "feat(db): add user_id to requirement_drafts and update unique constraint"
```

### Task 3: Settings Table & Model

**Files:**
- Create: `backend/database/migrations/2026_06_03_000003_create_settings_table.php`
- Create: `backend/app/Models/Setting.php`

- [ ] **Step 1: Create settings migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 2: Create Setting model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];
}
```

- [ ] **Step 3: Commit**

```bash
git add backend/database/migrations/2026_06_03_000003_create_settings_table.php backend/app/Models/Setting.php
git commit -m "feat(db): add settings table and model"
```

### Task 4: Run Migrations & Verification

**Files:**
- Test: `backend/tests/Feature/DatabaseUpdateTest.php`

- [ ] **Step 1: Run migrations**

Run: `php artisan migrate` in `backend` directory.

- [ ] **Step 2: Create verification test**

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RequirementDraft;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_has_new_fields()
    {
        $project = Project::factory()->create([
            'description' => 'Test description',
            'category' => 'Test category',
        ]);

        $this->assertEquals('Test description', $project->description);
        $this->assertEquals('Test category', $project->category);
    }

    public function test_requirement_draft_has_user_id()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        
        $draft = RequirementDraft::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'test',
            'version' => '1.0',
            'content' => [],
        ]);

        $this->assertEquals($user->id, $draft->user_id);
    }

    public function test_settings_table_works()
    {
        Setting::create(['key' => 'site_name', 'value' => 'AI Writer']);
        
        $setting = Setting::find('site_name');
        $this->assertEquals('AI Writer', $setting->value);
    }
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test tests/Feature/DatabaseUpdateTest.php`

- [ ] **Step 4: Commit and finalize**

```bash
git add backend/tests/Feature/DatabaseUpdateTest.php
git commit -m "test: verify database updates"
```
