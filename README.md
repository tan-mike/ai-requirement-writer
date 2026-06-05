# AI Requirement Writer

Automated tool to generate Business Requirements Documents (BRD), User Stories, and Technical Specifications using Google Gemini 2.0.

## 🌟 The Agentic Architecture

This system is an **Agentic AI Architect** designed for continuous product evolution. Projects are not isolated drafts; they are permanent containers for your product's lifecycle, powered by an evolving **Project Knowledge Base (PKB)**.

### Core Concepts

- **Project Containers:** Create a container for your product (e.g., "E-commerce Platform"). Projects act as the root context for all future enhancements.
- **Continuous Features:** Add multiple **Features** to a project over time. Each feature has its own discovery and generation lifecycle.
- **Knowledge Persistence:** Approved features are automatically committed to the PKB. The AI "remembers" past decisions, ensuring that new features never contradict established business logic or technical architecture.
- **Google Drive Sync:** All baseline documentation (Contexts) and Expert Agent definitions (Skills) are synced dynamically from Google Drive. Update your AI's instructions by simply editing a markdown file—no code changes required.

---

## 🏗 Tech Stack

- **Backend:** Laravel 11 (PHP 8.3)
- **Frontend:** Next.js 15 (React 19, TypeScript)
- **AI:** Google Gemini 2.0 Flash
- **Database:** MySQL 8.0

---

## 🚀 Setup & Deployment

### Option A: Local Docker (Single Container)

Builds the entire stack into a single container for easy local testing.

**Prerequisites:** Docker Desktop.

1. **Create Network & DB:**
   ```bash
   docker network create ai-writer-net
   docker run -d --name ai-db --network ai-writer-net -e MYSQL_DATABASE=ai_writer -e MYSQL_ROOT_PASSWORD=root_pass mysql:8
   ```

2. **Build & Run:**
   ```bash
   docker build -t ai-requirement-writer .
   docker run -it --rm -p 8080:80 --name ai-app --network ai-writer-net \
     -e DB_CONNECTION=mysql -e DB_HOST=ai-db -e DB_DATABASE=ai_writer -e DB_USERNAME=root -e DB_PASSWORD=root_pass \
     -e GEMINI_API_KEY=your_key \
     -e GOOGLE_DRIVE_CONTEXTS_FOLDER_ID=your_id \
     ai-requirement-writer
   ```

---

### Option B: Local Development

1. **Backend:** `composer install`, configure `.env`, `php artisan migrate --seed`, `php artisan queue:listen`.
2. **Frontend:** `npm install`, `npm run dev`.

---

## 🛠 Configuration (Google Drive Sync)

Dynamic sync is the **primary method** for managing contexts and skills.

1. **Service Account:** Create a Google Cloud Service Account and download the JSON key.
2. **Folder IDs:** Obtain Folder IDs for your Contexts and Personas folders.
3. **Environment Variables:**
   ```env
   GOOGLE_SERVICE_ACCOUNT_JSON=/path/to/credentials.json
   GOOGLE_DRIVE_FOLDER_ID=your_skills_folder_id
   GOOGLE_DRIVE_CONTEXTS_FOLDER_ID=your_contexts_folder_id
   ```
4. **Syncing:** Run `php artisan skills:sync` and `php artisan contexts:sync`.

---

## 💻 Workflow

### 1. Project Initialization
Create a **Project Container**. Link any relevant baseline documentation (Contexts) synced from Google Drive. These documents provide the foundation for every feature built within this project.

### 2. Feature Discovery
Add a new **Feature** to your project. Choose between:
- **AI Discovery Interview:** Conduct a "Challenger" interview where the AI grills your requirements to find the real need.
- **Structured Intake:** Fill out a framework-based form tailored to the feature type.

### 3. Sequential Generation
Generate your requirements in a logical sequence:
1. **BRD:** High-level business needs.
2. **User Stories:** Detailed functional breakdowns (unlocked after BRD approval).
3. **Technical Spec:** Technical implementation details (unlocked after Stories approval).

### 4. Expert Review & Synthesis
Optionally select **Reviewer Personas** (e.g., Security Lead, UI/UX Expert). They will audit your drafts and provide critiques. Use the **AI Synthesis** feature to automatically merge all feedback into a final, polished version.

### 5. Finalization
Once satisfied, **Finalize** the feature. This locks the requirements as read-only and commits them to the project's permanent knowledge base.
