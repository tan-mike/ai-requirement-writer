# Design Doc: File-based Skills (Google Drive Sync)

Date: 2026-06-01
Topic: File-based Skills Sync
Source of Truth: Google Drive
Target Architecture: AWS EC2 (Laravel Backend)

## 1. Overview
The goal is to move the source of truth for "Skills" (implemented as `Persona` models) from the application database to a Google Drive folder. The application will synchronize with this folder periodically, ensuring that updates made to `.md` files in Drive are automatically "hydrated" into the application database.

## 2. Architecture

### 2.1 Source of Truth
- A Google Drive folder containing `.md` files.
- Format: Markdown with YAML Frontmatter.
- The app will extract properties from frontmatter and use the body as the system prompt.
- **Lenient Validation**: The app will not strictly validate the skill content, allowing for flexible formats.

Example file:
```markdown
---
name: Expert Architect
role: lead
cost_multiplier: 1.2
is_global: true
is_active: true
---

# Expert Architect
You are a senior software architect with deep expertise in Laravel and Next.js...
```

### 2.2 Authentication
- **Service Account**: A Google Cloud Service Account will be used for server-to-server authentication.
- **Environment Variables**:
    - `GOOGLE_DRIVE_FOLDER_ID`: The ID of the Drive folder to monitor.
    - `GOOGLE_SERVICE_ACCOUNT_JSON`: Path to the service account credentials file (or JSON string).

### 2.3 Synchronization (Hydration)
- **Laravel Background Job**: `SyncSkillsFromGoogleDriveJob`.
- **Logic**:
    1. Authenticate with Google Drive API.
    2. Fetch all `.md` files in the specified `GOOGLE_DRIVE_FOLDER_ID`.
    3. For each file:
        - Identify if it exists in the `personas` table using `google_drive_id`.
        - Extract metadata and body.
        - If exists: Update attributes.
        - If new: Create new `Persona`.
    4. (Optional) Deactivate/Delete personas that no longer exist in Drive.
- **Schedule**: Polling every 15 minutes (configurable).

## 3. Data Model Changes

### 3.1 `personas` table
Add the following column:
- `google_drive_id` (string, unique, nullable): Stores the unique ID of the file in Google Drive.

## 4. Components

### 4.1 `GoogleDriveSyncService`
- Responsibilities:
    - Encapsulate Google Client initialization.
    - List files in folder.
    - Download file content.

### 4.2 `SyncSkillsFromGoogleDriveJob`
- Responsibilities:
    - Orchestrate the sync process.
    - Handle database transactions.

### 4.3 `SkillSyncCommand`
- Artisan command `skills:sync` for manual triggers.

## 5. Security & Availability
- **Resilience**: The app uses a database cache (Sync to DB) so it doesn't fail if Google Drive is temporarily unavailable.
- **Access Control**: Only the folder shared with the Service Account will be accessible.

## 6. Implementation Plan
1. Install `google/apiclient`.
2. Install a markdown frontmatter parser (e.g., `spatie/yaml-front-matter`).
3. Create migration for `google_drive_id`.
4. Implement `GoogleDriveSyncService`.
5. Implement `SyncSkillsFromGoogleDriveJob`.
6. Register scheduled task in `routes/console.php`.
7. (Optional) Update `SkillController` to allow manual sync triggers or hide upload if disabled.
