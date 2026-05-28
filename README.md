# AI Requirement Writer

Automated tool to generate Business Requirements Documents (BRD), User Stories, and Technical Specifications using Google Gemini 2.0.

## Features

- **Multi-Step Generation:** Guided flow from BRD → User Stories → Technical Spec.
- **Flexible Context:** Start from scratch, upload files, or select from your library of reusable **Saved Contexts**.
- **Template Mode:** Quick generation based on structured project intake forms.
- **Conversational Mode (AI Interview):** A "Principal Architect" AI grills you to distill true business requirements.
- **Codebase Awareness:** Connect GitHub or local repos. The AI analyzes your existing architecture to ensure new features maintain system integrity.
- **Agentic Persona System:** Select specialized Lead personas (Fintech, Enterprise) and a Review Committee (Security, UX, Performance) for multi-agent refinement.
- **Reusable Contexts:** Save and name technical context snippets for reuse across generations.
- **Markdown Editor:** Review, edit, and version your requirement drafts.

---

## Architecture

- **Backend:** Laravel 13 (PHP 8.3)
- **Frontend:** Next.js 16 (React 19, TypeScript)
- **AI:** Google Gemini 2.0 Flash (via `google-gemini-php/laravel`)
- **Database:** SQLite (default) or MySQL

---

## Setup & Deployment

### Option 1: Local Development (Manual)

Best for development and active contribution.

**Backend:**
1. `cd backend && composer install`
2. `cp .env.example .env` (Set `GEMINI_API_KEY`)
3. `php artisan key:generate`
4. `php artisan migrate --seed`
5. `php artisan queue:listen --timeout=3600` (In a separate terminal)
6. `php artisan serve` (Runs on `http://localhost:8000`)

**Frontend:**
1. `cd frontend && npm install`
2. `npm run dev` (Runs on `http://localhost:3000`)

---

### Option 2: Docker / Hosted (Unified)

Best for production, internal team hosting, or quick evaluation. This builds the frontend and backend into a **single container** served from a **single address**.

1. **Build the image:**
   ```bash
   docker build -t ai-requirement-writer .
   ```

2. **Run the container:**
   ```bash
   docker run -p 80:80 \
     -e GEMINI_API_KEY=your_key_here \
     -e DB_CONNECTION=mysql \
     -e DB_HOST=your_rds_host \
     -e QUEUE_WORKER_COUNT=4 \
     ai-requirement-writer
   ```

**Features of the Unified Docker Image:**
- **Single Port:** Everything (Frontend + API) is served on port 80.
- **Standalone Frontend:** Next.js runs in standalone mode for full dynamic route support.
- **Auto-Scale Workers:** Set `QUEUE_WORKER_COUNT` to handle concurrent AI reviews.
- **Self-Healing:** Managed by Supervisor inside the container.

---

## Usage

1. **Register/Login:** Create an account to manage projects.
2. **New Project:** 
   - Choose **Template Mode** for quick forms.
   - Choose **Conversational Mode** to be interviewed by the AI.
   - (Optional) Link a **GitHub URL** or **Local Path** to give the AI context of your existing codebase.
3. **Discovery:**
   - **Template:** Fill in project details (goals, features, constraints).
   - **Conversational:** Answer the AI's probing questions. The AI will push back if your ideas conflict with existing architecture. Type until the AI determines requirements are ready.
4. **Generate:**
   - **BRD:** Generate the Business Requirements Document.
   - **User Stories:** Once BRD is approved, generate stories.
   - **Spec:** Once stories are approved, generate Technical Specification.
5. **Review & Edit:** Edit generated drafts in the markdown editor before approval.

---

## Hosting & Deployment

### Backend (Laravel)

- **Server:** Any PHP 8.3 compatible environment (Forge, DigitalOcean, Vapor).
- **Env Vars:** Ensure `APP_KEY`, `DB_*`, and `GEMINI_API_KEY` are set.
- **Worker:** You **MUST** run a persistent queue worker (e.g., via Supervisor) to handle repository ingestion: `php artisan queue:work`.
- **Optimization:** Run `php artisan optimize` in production.

### AWS (Enterprise/Internal)
For internal team deployments (10-20 users) requiring high-concurrency AI streaming, see the [AWS Deployment Guide](./DEPLOYMENT_AWS.md).

### Frontend (Next.js)

- **Platform:** Deploy to Vercel (recommended), Netlify, or a VPS.
- **Env Vars:** Set `NEXT_PUBLIC_API_URL` to your backend API endpoint.
- **Build:** `npm run build`
