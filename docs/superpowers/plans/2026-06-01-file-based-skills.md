# File-based Skills (Google Drive Sync) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move skill (Persona) management to Google Drive `.md` files with automatic database hydration, supporting multiple auth methods.

**Architecture:** A Laravel scheduled job polls a Google Drive folder for `.md` files, parses YAML frontmatter for metadata, and updates/creates `Persona` records in the database. Supports both Service Account and OAuth 2.0 auth.

**Tech Stack:** Laravel, `google/apiclient`, `spatie/yaml-front-matter`.

---

### Task 1: Dependencies and Environment

**Files:**
- Modify: `backend/composer.json`
- Modify: `.env.example`

- [ ] **Step 1: Install required packages**

Run:
```bash
cd backend
composer require google/apiclient spatie/yaml-front-matter
```

- [ ] **Step 2: Add environment variables to .env.example**

Add:
```text
GOOGLE_DRIVE_FOLDER_ID=
GOOGLE_AUTH_METHOD=service_account # or 'oauth'

# For Service Account
GOOGLE_SERVICE_ACCOUNT_JSON=

# For OAuth 2.0
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REFRESH_TOKEN=
```

- [ ] **Step 3: Commit**

```bash
git add backend/composer.json backend/composer.lock .env.example
git commit -m "chore: add google-api and frontmatter dependencies"
```

---

### Task 2: Database Migration

**Files:**
- Create: `backend/database/migrations/2026_06_01_000000_add_google_drive_id_to_personas_table.php`
- Modify: `backend/app/Models/Persona.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personas', function (Blueprint $table) {
            $table->string('google_drive_id')->nullable()->unique()->after('id');
            $table->boolean('is_global')->default(false)->after('cost_multiplier');
        });
    }

    public function down(): void {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['google_drive_id', 'is_global']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd backend && php artisan migrate`

- [ ] **Step 3: Update Persona Model fillable**

Modify `backend/app/Models/Persona.php`:
```php
protected $fillable = [
    'user_id',
    'slug',
    'name',
    'role',
    'system_prompt',
    'cost_multiplier',
    'is_global',
    'is_active',
    'google_drive_id',
];
```

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/ backend/app/Models/Persona.php
git commit -m "feat: add google_drive_id to personas table"
```

---

### Task 3: Configuration

**Files:**
- Modify: `backend/config/services.php`

- [ ] **Step 1: Register Google Drive service config**

Add to `backend/config/services.php`:
```php
'google' => [
    'drive' => [
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'auth_method' => env('GOOGLE_AUTH_METHOD', 'service_account'),
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
    ],
],
```

- [ ] **Step 2: Commit**

```bash
git add backend/config/services.php
git commit -m "config: add multi-auth google drive configuration"
```

---

### Task 4: GoogleDriveSyncService Implementation

**Files:**
- Create: `backend/app/Services/GoogleDriveSyncService.php`

- [ ] **Step 1: Write the service class with multi-auth support**

```php
<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Config;

class GoogleDriveSyncService
{
    protected Drive $service;

    public function __construct()
    {
        $client = new Client();
        $config = Config::get('services.google.drive');

        if ($config['auth_method'] === 'oauth') {
            $client->setClientId($config['client_id']);
            $client->setClientSecret($config['client_secret']);
            $client->refreshToken($config['refresh_token']);
        } else {
            $auth = $config['service_account_json'];
            if (file_exists($auth)) {
                $client->setAuthConfig($auth);
            } else {
                $client->setAuthConfig(json_decode($auth, true));
            }
        }

        $client->addScope(Drive::DRIVE_READONLY);
        $this->service = new Drive($client);
    }

    public function listFiles(string $folderId): array
    {
        $query = "'$folderId' in parents and mimeType = 'text/markdown' and trashed = false";
        $results = $this->service->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name, modifiedTime)'
        ]);

        return $results->getFiles();
    }

    public function getFileContent(string $fileId): string
    {
        $response = $this->service->files->get($fileId, ['alt' => 'media']);
        return $response->getBody()->getContents();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/app/Services/GoogleDriveSyncService.php
git commit -m "feat: implement GoogleDriveSyncService with multi-auth"
```

---

### Task 5: SyncSkillsFromGoogleDriveJob Implementation

**Files:**
- Create: `backend/app/Jobs/SyncSkillsFromGoogleDriveJob.php`

- [ ] **Step 1: Write the sync job**

```php
<?php

namespace App\Jobs;

use App\Models\Persona;
use App\Services\GoogleDriveSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class SyncSkillsFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleDriveSyncService $driveService): void
    {
        $folderId = config('services.google.drive.folder_id');
        $files = $driveService->listFiles($folderId);

        foreach ($files as $file) {
            $content = $driveService->getFileContent($file->id);
            $document = YamlFrontMatter::parse($content);

            $data = [
                'name' => $document->matter('name', Str::title(str_replace('.md', '', $file->name))),
                'role' => $document->matter('role', 'lead'),
                'cost_multiplier' => (float) $document->matter('cost_multiplier', 1.0),
                'is_global' => (bool) $document->matter('is_global', true),
                'is_active' => (bool) $document->matter('is_active', true),
                'system_prompt' => trim($document->body()),
            ];

            Persona::updateOrCreate(
                ['google_drive_id' => $file->id],
                [
                    'name' => $data['name'],
                    'role' => $data['role'],
                    'system_prompt' => $data['system_prompt'],
                    'cost_multiplier' => $data['cost_multiplier'],
                    'is_global' => $data['is_global'],
                    'is_active' => $data['is_active'],
                    'slug' => Persona::where('google_drive_id', $file->id)->value('slug') 
                              ?? Str::slug($data['name'] . '-' . Str::random(5)),
                ]
            );
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/app/Jobs/SyncSkillsFromGoogleDriveJob.php
git commit -m "feat: implement SyncSkillsFromGoogleDriveJob"
```

---

### Task 6: Artisan Command and Scheduling

**Files:**
- Create: `backend/app/Console/Commands/SyncSkillsCommand.php`
- Modify: `backend/routes/console.php`

- [ ] **Step 1: Create Artisan command**

```php
<?php

namespace App\Console\Commands;

use App\Jobs\SyncSkillsFromGoogleDriveJob;
use Illuminate\Console\Command;

class SyncSkillsCommand extends Command
{
    protected $signature = 'skills:sync';
    protected $description = 'Sync skills from Google Drive';

    public function handle(): void
    {
        $this->info('Starting skills sync...');
        SyncSkillsFromGoogleDriveJob::dispatchSync();
        $this->info('Skills sync completed.');
    }
}
```

- [ ] **Step 2: Register schedule in routes/console.php**

Add:
```php
use App\Jobs\SyncSkillsFromGoogleDriveJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncSkillsFromGoogleDriveJob)->everyFifteenMinutes();
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Console/Commands/SyncSkillsCommand.php backend/routes/console.php
git commit -m "feat: add skills:sync command and schedule job"
```

---

### Task 7: Final Verification and Cleanup

**Files:**
- Modify: `backend/app/Http/Controllers/SkillController.php`

- [ ] **Step 1: Update index in SkillController**

- [ ] **Step 2: Commit**

```bash
git commit -m "chore: final adjustments for skills sync"
```
