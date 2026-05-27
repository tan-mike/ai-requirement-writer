# Unified Docker Deployment Specification

## Goal
Deploy the Next.js frontend and Laravel backend as a single deployable artifact (Docker image) to simplify AWS deployment. The architecture defaults to a "Monolith" (web + worker in one container) for simplicity, but is structured to allow splitting into separate web and worker containers if demand increases.

## Architecture

We will use a multi-stage Docker build to compile both the frontend and backend, merging them into a final, lightweight Alpine Linux image.

### 1. Multi-Stage Dockerfile
*   **Stage 1: Frontend Build (Node.js)**
    *   Install dependencies.
    *   Build Next.js as a static export (`output: 'export'`).
*   **Stage 2: Backend Build (Composer)**
    *   Install PHP dependencies (no-dev, optimized autoloader).
*   **Stage 3: Production Runtime (Alpine Linux)**
    *   Install Nginx, PHP 8.3-FPM, and Supervisor.
    *   Copy static frontend assets from Stage 1 to Nginx's document root.
    *   Copy Laravel application from Stage 2.
    *   Set appropriate permissions for Laravel's `storage` and `bootstrap/cache`.

### 2. Process Management (Supervisor)
`supervisord` will act as the PID 1 process, managing:
1.  **Nginx:** Serving static files and acting as a reverse proxy.
2.  **PHP-FPM:** Executing backend logic.
3.  **Laravel Queue Worker:** Processing asynchronous AI generation jobs.
    *   *Configurability:* We will use an initialization script (`entrypoint.sh`) that reads a `QUEUE_WORKER_COUNT` environment variable (default: 1) and dynamically updates the Supervisor configuration before starting it.

### 3. Nginx Configuration
*   **Static Serving:** Any request not matching a file will fall back to `index.html` (for Next.js client-side routing).
*   **API Proxying:** Requests to `/api/*` and `/sanctum/csrf-cookie` will be forwarded to PHP-FPM.

## Required Code Changes

1.  **`frontend/next.config.ts`**
    *   Add `output: 'export'`.
    *   Disable image optimization if using Next.js Image component (unsupported in static exports without external loaders).
2.  **`frontend/lib/api.ts`**
    *   Ensure the API calls use relative paths (e.g., `/api`) so they work seamlessly behind the unified Nginx proxy without needing hardcoded environment variables at build time.
3.  **Add Configuration Files:**
    *   `docker/nginx.conf`
    *   `docker/supervisord.conf`
    *   `docker/entrypoint.sh`
    *   `Dockerfile` (in the project root).
