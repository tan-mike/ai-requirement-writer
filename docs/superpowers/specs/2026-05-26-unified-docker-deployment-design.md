# Unified Docker Deployment Specification

## Goal
Deploy the Next.js frontend and Laravel backend as a single deployable artifact (Docker image) to simplify AWS deployment. The architecture defaults to a "Monolith" (web + worker in one container) for simplicity, but is structured to allow splitting into separate web and worker containers if demand increases.

## Architecture

We will use a multi-stage Docker build to compile both the frontend and backend, merging them into a final, lightweight Alpine Linux image.

### 1. Multi-Stage Dockerfile
*   **Stage 1: Frontend Build (Node.js)**
    *   Install dependencies.
    *   Build Next.js in Standalone Mode (`output: 'standalone'`).
*   **Stage 2: Backend Build (Composer)**
    *   Install PHP dependencies (no-dev, optimized autoloader).
*   **Stage 3: Production Runtime (Alpine Linux)**
    *   Install Nginx, Node.js, PHP 8.3-FPM, and Supervisor.
    *   Copy standalone frontend files from Stage 1.
    *   Copy Laravel application from Stage 2.
    *   Set appropriate permissions for Laravel's `storage` and `bootstrap/cache`.

### 2. Process Management (Supervisor)
`supervisord` will act as the PID 1 process, managing:
1.  **Nginx:** Acts as a unified entry point (reverse proxy).
2.  **Next.js (Frontend):** Runs the standalone server on localhost:3000.
3.  **PHP-FPM (Backend):** Executing backend logic on localhost:9000.
4.  **Laravel Queue Worker:** Processing asynchronous AI generation jobs.
    *   *Configurability:* We will use an initialization script (`entrypoint.sh`) that reads a `QUEUE_WORKER_COUNT` environment variable (default: 1) and dynamically updates the Supervisor configuration before starting it.

### 3. Nginx Configuration
*   **Frontend Proxying:** Requests to `/` will be forwarded to the Next.js standalone server (localhost:3000).
*   **API Proxying:** Requests to `/api/*` and `/sanctum/csrf-cookie` will be forwarded to PHP-FPM (localhost:9000).

## Required Code Changes

1.  **`frontend/next.config.ts`**
    *   Add `output: 'standalone'`.
2.  **`frontend/lib/api.ts`**
    *   Ensure the API calls use relative paths (e.g., `/api`) so they work seamlessly behind the unified Nginx proxy without needing hardcoded environment variables at build time.
3.  **Add Configuration Files:**
    *   `docker/nginx.conf`
    *   `docker/supervisord.conf`
    *   `docker/entrypoint.sh`
    *   `Dockerfile` (in the project root).
