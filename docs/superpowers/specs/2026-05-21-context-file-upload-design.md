# Context File Upload — Design Spec
**Date:** 2026-05-21
**Author:** Gemini CLI
**Status:** Draft

---

## 1. Problem

Linking a full repository can be slow or exceed token limits for very large projects. Users often already have a condensed representation of their project (e.g., a `CONTEXT.md` or a structured JSON/XML summary) that they want to use as the foundation for requirement generation without waiting for repository cloning and AI-based architecture summarization.

---

## 2. Solution

Allow users to upload a single context file during project creation. The content of this file will be used directly as the `architecture_summary` for the project.

**Supported Formats:**
- Markdown (`.md`)
- Plain Text (`.txt`)
- JSON (`.json`)
- XML (`.xml`)

---

## 3. Architecture & Flow

### Asynchronous Processing Flow

1. **User Action:** User selects a context file in the "New Project" UI.
2. **API Request:** Frontend sends a `multipart/form-data` POST request to `/api/projects`.
3. **Controller Handling:** 
   - Laravel validates the file (max 2MB, allowed extensions).
   - Project is created with `status: 'processing'`.
   - File is stored in a temporary directory (`storage/app/temp_context/`).
   - `ProcessContextFileJob` is dispatched with the project and file path.
4. **Background Job:**
   - Reads the file content.
   - Updates `project.architecture_summary` with the raw content.
   - Updates `project.status` to `ready`.
   - Deletes the temporary file.
5. **UI Update:** Frontend polls or refreshes to show the project is ready for requirement generation.

---

## 4. Technical Changes

### Backend (Laravel)

#### Model Update
No schema changes required. `projects.architecture_summary` and `projects.status` already exist.

#### Request Validation (`StoreProjectRequest`)
Add `context_file` field:
- `file`
- `max:2048` (2MB)
- `mimes:txt,json,xml,md`

#### Job (`App\Jobs\ProcessContextFileJob`)
New job to handle the file reading and project update.

#### Controller (`App\Http\Controllers\ProjectController`)
Update `store` method to check for `context_file` and dispatch the new job instead of `ProcessRepositoryJob` if a file is provided.

### Frontend (Next.js)

#### New Project Form
- Add a file input for "Context File".
- Ensure the form sends `multipart/form-data` when a file is selected.
- Logic to toggle between "Repository URL" and "Context File" upload.

---

## 5. Testing Strategy

- **Unit Test:** `ProcessContextFileJob` correctly reads file content and updates the model.
- **Feature Test:** `ProjectController@store` handles file uploads, stores them temporarily, and dispatches the job.
- **Integration Test:** Full flow from upload to `ready` status verification.

---

## 6. Out of Scope

- Multi-file uploads (single context file only).
- Automatic "splitting" of very large context files.
- AI-based summarization of the context file (the content is assumed to be the summary).
