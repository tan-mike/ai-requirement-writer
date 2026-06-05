# Curated Projects Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a dedicated Admin Dashboard to manage curated projects and global settings, allowing users to create requirements within shared curated projects.

**Architecture:** Dedicated Admin controllers and routes in Laravel, with a restricted Admin layout and pages in Next.js. Shared project model with per-user requirement drafts.

**Tech Stack:** Laravel (PHP), Next.js (TypeScript/React), Tailwind CSS, MySQL.

---

### Task 1: Database Migrations & Models

**Files:**
- Create: `backend/database/migrations/YYYY_MM_DD_HHMMSS_add_description_to_projects.php`
- Create: `backend/database/migrations/YYYY_MM_DD_HHMMSS_add_user_id_to_requirement_drafts.php`
- Create: `backend/database/migrations/YYYY_MM_DD_HHMMSS_create_settings_table.php`
- Create: `backend/app/Models/Setting.php`
- Modify: `backend/app/Models/Project.php`
- Modify: `backend/app/Models/RequirementDraft.php`

- [ ] **Step 1: Add description/category to projects**
```php
Schema::table('projects', function (Blueprint $table) {
    $table->text('description')->nullable()->after('name');
    $table->string('category')->nullable()->after('is_curated');
});
```

- [ ] **Step 2: Add user_id to requirement_drafts and update unique constraint**
```php
Schema::table('requirement_drafts', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();
    $table->dropUnique(['project_id', 'type', 'version']);
    $table->unique(['project_id', 'type', 'version', 'user_id']);
});
```

- [ ] **Step 3: Create Settings table**
```php
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Update Models**
Update `Project` and `RequirementDraft` `$fillable` arrays. Add `Setting` model.

- [ ] **Step 5: Run Migrations**
Run: `php artisan migrate`

---

### Task 2: Admin API Routes & Controllers

**Files:**
- Create: `backend/app/Http/Controllers/Admin/ProjectCurationController.php`
- Create: `backend/app/Http/Controllers/Admin/SettingController.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Define Admin Routes**
Add a protected admin group in `api.php`.
```php
Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin')->group(function () {
    Route::get('/projects', [ProjectCurationController::class, 'index']);
    Route::patch('/projects/{project}/curate', [ProjectCurationController::class, 'update']);
    Route::post('/projects', [ProjectCurationController::class, 'store']); // Create curated
    Route::get('/settings', [SettingController::class, 'index']);
    Route::patch('/settings', [SettingController::class, 'update']);
});
```

- [ ] **Step 2: Implement ProjectCurationController**
Allow admins to list all projects, toggle curation, and create new projects specifically marked as curated.

- [ ] **Step 3: Implement SettingController**
Simple CRUD for the `settings` table.

---

### Task 3: Access Control & Context Inheritance Logic

**Files:**
- Modify: `backend/app/Http/Controllers/ProjectController.php`
- Modify: `backend/app/Http/Controllers/RequirementDraftController.php`
- Modify: `backend/app/Http/Controllers/GenerationController.php`

- [ ] **Step 1: Update Project Visibility**
Modify `ProjectController@show` to allow access to curated projects even if the user doesn't own them.

- [ ] **Step 2: Filter Drafts by User**
Modify `RequirementDraftController` methods to ensure `user_id` is checked when accessing drafts on curated projects.
```php
$query->where('user_id', $request->user()->id);
```

- [ ] **Step 3: Assign User to Drafts**
Update `GenerationController` to set `user_id` when creating new drafts.

- [ ] **Step 4: Global Settings Check**
Update `ProjectController@store` to check the `settings` table for the `creation_disabled` flag.

---

### Task 4: Frontend Admin Layout & Dashboard

**Files:**
- Create: `frontend/app/(dashboard)/admin/layout.tsx`
- Create: `frontend/app/(dashboard)/admin/page.tsx`
- Create: `frontend/app/(dashboard)/admin/projects/page.tsx`
- Create: `frontend/app/(dashboard)/admin/settings/page.tsx`

- [ ] **Step 1: Admin Guard Layout**
Create a layout that redirects non-admins and provides a sidebar/nav for admin sections.

- [ ] **Step 2: Project Management Page**
List all projects with "Curate" toggle and "Category" edit. Add "Add Curated Project" button/modal.

- [ ] **Step 3: Settings Page**
UI for toggling global permissions.

---

### Task 5: User Dashboard Updates

**Files:**
- Modify: `frontend/app/(dashboard)/dashboard/page.tsx`
- Modify: `frontend/components/ProjectCard.tsx`

- [ ] **Step 1: Display Curated Projects**
Ensure curated projects are highlighted.

- [ ] **Step 2: Start Requirement Workflow**
Update project cards to have a "Start Requirement" button that deep-links to the editor for that project.
