# Design Spec: Curated Projects Admin Page (Revised v3)

## Overview
This feature introduces a dedicated Admin Dashboard to manage "Curated Projects" and global permissions. Curated projects serve as shared templates where normal users can create their own "Requirement Sets" (Drafts) without needing to create a full project.

## Objectives
1.  **Project Curation**: Admins create projects with specific contexts (docs/repos) and mark them as curated.
2.  **Shared Inheritance**: When a user selects a curated project, its contexts are automatically used for AI generation of their requirements.
3.  **User-Owned Requirements**: Multiple users can create independent requirements within a single curated project.
4.  **Global Permissions**: Admins can toggle whether normal users are allowed to create their own projects from scratch.

## Architecture

### Frontend (Next.js)
-   **Admin Dashboard**: `/admin`
    -   **Projects**: Manage curated projects (Name, Description, Category, Contexts).
    -   **Settings**: Toggle "Allow Member Project Creation".
-   **Dashboard (Users)**:
    -   Displays "Curated Projects" as the primary entry point.
    -   "Start New Requirement" button on a curated project card.
    -   Listing of "My Requirements" (Drafts owned by the user across projects).

### Backend (Laravel)
-   **Models**:
    -   `Project`: Add `category` (string), `description` (text).
    -   `RequirementDraft`: Add `user_id` (foreignId).
    -   `Setting`: New model for key-value global config.
-   **Logic Updates**:
    -   `ProjectController@show`: Allow access if `is_curated === true`.
    -   `RequirementDraftController`: Update index/show/update/approve to filter by `user_id` if the project is curated.
    -   `GenerationController`: Set `user_id` on newly created drafts.
    -   `GeminiGenerationService`: Automatically inherits `Project` contexts (already supported via project relationship).

## Data Schema Changes

### `projects` table
-   Add `description` (text, nullable).
-   Add `category` (string, nullable).

### `requirement_drafts` table
-   Add `user_id` (foreignId, nullable).
-   **Important**: Update unique constraint to `['project_id', 'type', 'version', 'user_id']` to allow multiple users to have independent draft sequences on the same curated project.

### `settings` table (New)
-   `key` (string, primary)
-   `value` (text)

## User Experience

### Admin Creation of Curated Projects
-   Fields: Name, Description (optional), Category selection.
-   Context Selection: Admins select which Knowledge Modules or Context Files are bundled with this project.

### User Interaction
1.  User logs in and sees a list of "Curated Projects" (e.g., "Standard Web App", "Microservice Template").
2.  User clicks "Create Requirement".
3.  User is taken to a Requirement Editor. Their draft is saved with their `user_id` and the `project_id` of the curated project.
4.  AI uses the curated project's contexts for all generations/critiques.

## Success Criteria
-   Admins can efficiently manage example projects.
-   Users can quickly start requirements without setup overhead.
-   Clear separation of user data within shared curated projects.
-   Access control prevents users from seeing each other's drafts on curated projects.
