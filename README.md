# AI Requirement Writer

Automated tool to generate Business Requirements Documents (BRD), User Stories, and Technical Specifications using Google Gemini 2.0.

## 🌟 The Agentic Evolution

This system has evolved from a static document generator into an **Agentic AI Architect**. Projects are no longer isolated drafts; they are living products managed via a **Project Knowledge Base (PKB)**.

- **Curated Modules:** Administrators define core system modules (e.g., "Core API", "Sales Dashboard"). Users build specific "Features" on top of these curated modules.
- **Agentic Memory:** Approved features are committed to the PKB. When generating new features, the AI dynamically retrieves relevant past decisions to ensure architectural consistency.
- **Hierarchical Contexts:** 
  - **Level 1 (Company Context):** Universal mandates applied to all generations.
  - **Level 2 (Module Context):** Business logic specific to the product line.
  - **Level 3 (Project History):** RAG-retrieved memory from previously approved features.
- **Google Drive Sync:** Company contexts, module guidelines, and AI Personas (Skills) are synced dynamically from Google Drive markdown files, allowing business owners to update AI instructions without code deployments.

---

## 🏗 Architecture

- **Backend:** Laravel 11 (PHP 8.3)
- **Frontend:** Next.js 15 (React 19, TypeScript)
- **AI:** Google Gemini 2.0 Flash (via `google-gemini-php/laravel`)
- **Database:** MySQL (Production) / SQLite (Local)

---

## 🚀 Setup & Deployment

### Option A: Local Docker (Recommended for Testing/Internal Tooling)

This builds the frontend and backend into a **single container** served from a **single address**.

**Prerequisites:** Docker Desktop installed.

1. **Create a Docker network:**
   ```bash
   docker network create ai-writer-net
   ```

2. **Launch a local MySQL database:**
   ```bash
   docker run -d \
     --name ai-db \
     --network ai-writer-net \
     -e MYSQL_DATABASE=ai_writer \
     -e MYSQL_ROOT_PASSWORD=root_pass \
     mysql:8
   ```

3. **Build the application image:**
   ```bash
   docker build -t ai-requirement-writer .
   ```

4. **Run the application:**
   ```bash
   docker run -it --rm \
     -p 8080:80 \
     --name ai-app \
     --network ai-writer-net \
     -e DB_CONNECTION=mysql \
     -e DB_HOST=ai-db \
     -e DB_PORT=3306 \
     -e DB_DATABASE=ai_writer \
     -e DB_USERNAME=root \
     -e DB_PASSWORD=root_pass \
     -e GEMINI_API_KEY=your_gemini_key_here \
     -e GOOGLE_DRIVE_CONTEXTS_FOLDER_ID=your_drive_folder_id \
     -e QUEUE_WORKER_COUNT=2 \
     ai-requirement-writer
   ```

5. **Access the App:** Open `http://localhost:8080` in your browser.

---

### Option B: Local Development (Manual)

Best for active development.

**Backend:**
1. `cd backend && composer install`
2. `cp .env.example .env` 
3. Configure `.env` with DB details, `GEMINI_API_KEY`, and Google Drive IDs.
4. `php artisan key:generate && php artisan migrate --seed`
5. Start the queue: `php artisan queue:listen --timeout=3600` (in a separate terminal)
6. Start the server: `php artisan serve`

**Frontend:**
1. `cd frontend && npm install`
2. `npm run dev`

---

### Option C: AWS Deployment (Enterprise)

For internal team deployments (10-20 users) requiring high-concurrency AI streaming, refer to the detailed [AWS Deployment Guide](./DEPLOYMENT_AWS.md).

---

## 🛠 Configuration (Google Drive Sync)

To enable the dynamic synchronization of Contexts and Personas (Skills) from Google Drive, you need to configure a Google Cloud Service Account.

**📖 For a complete, step-by-step walkthrough, see the [Google Drive & Service Account Setup Guide](./docs/GOOGLE_DRIVE_SETUP.md).**

### Quick Reference:
1. Add the following to your `.env` (or pass to Docker):
   ```env
   # Path to your JSON or the raw JSON string
   GOOGLE_SERVICE_ACCOUNT_JSON=/path/to/credentials.json
   
   # Folder ID for Personas/Skills (.md files with YAML frontmatter)
   GOOGLE_DRIVE_FOLDER_ID=your_skills_folder_id
   
   # Folder ID for Contexts (.md files with YAML frontmatter)
   GOOGLE_DRIVE_CONTEXTS_FOLDER_ID=your_contexts_folder_id
   ```
2. Run `php artisan skills:sync` (or let the automated scheduler handle it).

**Context Hierarchy Note:**
- Files in the Contexts folder with `is_global: true` in their frontmatter become Level 1 Company Contexts.
- Files with `is_global: false` are treated as Level 2 Module Contexts and must be manually attached to curated projects via the admin UI.

---

## 💻 Usage Flow

1. **Admin Setup:**
   - Log in as an administrator.
   - Wait for Contexts and Skills to sync from Google Drive.
   - Create **Curated Projects** (Modules) representing your core product lines (e.g., "SANSOLS", "SAFHIS"). Attach the relevant Module Contexts to these projects.

2. **User Feature Development:**
   - Users log in and see the list of Curated Projects. Project creation from scratch is disabled for standard users by default.
   - Select a Curated Project to build a new feature.
   - Enter the **Feature Name** and **Description**, and select the primary Lead Persona (Skill) for the task.

3. **AI Generation & Review:**
   - The AI generates the requirement draft (BRD, User Story, or Spec) utilizing the full Context Hierarchy (Company -> Module -> Project History -> Feature Intent).
   - If selected, Reviewer Personas (e.g., "Security Auditor") will critique the draft. The workflow will transition to a "Refining" state until issues are addressed.

4. **Approval & Project Evolution:**
   - Once a draft is finalized, the user clicks "Approve".
   - The system automatically transitions the draft to the `committed` state, converting it into a permanent **Knowledge Module**.
   - Future features generated for this Curated Project will automatically 'remember' and adhere to this newly committed logic.
