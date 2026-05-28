# Unified Dockerfile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a multi-stage Dockerfile for a unified standalone production image containing both Next.js frontend and Laravel backend.

**Architecture:** 3-stage build: 1. Frontend build (Node.js), 2. Backend build (PHP/Composer), 3. Runtime (PHP-FPM + Nginx + Supervisor).

**Tech Stack:** Docker, Next.js (Standalone), Laravel (PHP 8.3-FPM), Alpine Linux.

---

### Task 1: Create Unified Dockerfile

**Files:**
- Create: `Dockerfile`

- [ ] **Step 1: Write multi-stage Dockerfile**

```dockerfile
# Stage 1: Frontend
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY frontend/package*.json ./
RUN npm install
COPY frontend/ .
RUN npm run build

# Stage 2: Backend
FROM php:8.3-fpm-alpine AS backend-build
WORKDIR /var/www/html
COPY backend/composer*.json ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader
COPY backend/ .

# Stage 3: Runtime
FROM php:8.3-fpm-alpine
RUN apk add --no-cache nginx supervisor nodejs
WORKDIR /var/www/html

# Copy Frontend Standalone
COPY --from=frontend-build /app/frontend/.next/standalone /var/www/html/frontend
COPY --from=frontend-build /app/frontend/.next/static /var/www/html/frontend/.next/static
COPY --from=frontend-build /app/frontend/public /var/www/html/frontend/public

# Copy Backend
COPY --from=backend-build /var/www/html/backend /var/www/html/backend
RUN chown -R www-data:www-data /var/www/html/backend/storage /var/www/html/backend/bootstrap/cache

# Configs
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
```

- [ ] **Step 2: Verify file creation**

Run: `ls -l Dockerfile`
Expected: File exists with correct permissions.

- [ ] **Step 3: Commit**

```bash
git add Dockerfile
git commit -m "infra: add unified multi-stage Dockerfile for standalone mode"
```
