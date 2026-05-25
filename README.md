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

## Setup & Local Development

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+ & NPM
- Git (for repository analysis)
- Google Gemini API Key ([Get one here](https://aistudio.google.com/app/apikey))

### Backend Setup

1. `cd backend`
2. `composer install`
3. `cp .env.example .env`
4. Set your `GEMINI_API_KEY` in `.env`
5. `php artisan key:generate`
6. `touch database/database.sqlite` (if using SQLite)
7. `php artisan migrate --seed`
8. **Seed Personas:** `php artisan db:seed --class=PersonaSeeder`
9. **Run the Queue:** `php artisan queue:listen --timeout=3600` (Required for repository analysis and agentic reviews).
    *   *Scale:* You can safely run multiple queue workers in parallel to speed up multi-persona reviews; the system uses atomic database updates to prevent race conditions.
10. **Start Server:** `php artisan serve` (Runs on `http://localhost:8000`)

### Frontend Setup

1. `cd frontend`
2. `npm install`
3. `npm run dev` (Runs on `http://localhost:3000`)

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
