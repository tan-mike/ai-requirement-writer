# Unified Docker Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single Docker image containing the Next.js static frontend, Laravel backend, and configurable queue workers, served via Nginx.

**Architecture:** Multi-stage Docker build producing an Alpine-based image running Nginx, PHP-FPM, and Supervisor.

**Tech Stack:** Docker, Nginx, PHP 8.3-FPM, Supervisor, Node.js (build-time).

---

### Task 1: Frontend Static Export Configuration

**Files:**
- Modify: `frontend/next.config.ts`

- [ ] **Step 1: Update Next.js config**

Add `output: 'export'` and disable image optimization.

```typescript
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'export',
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
```

- [ ] **Step 2: Verify build command locally**

Run: `cd frontend && npm run build`
Expected: `frontend/out` directory created with static files.

- [ ] **Step 3: Commit**

```bash
git add frontend/next.config.ts
git commit -m "chore(frontend): enable static export for docker deployment"
```

---

### Task 2: Backend & API Relative Path Adjustments

**Files:**
- Modify: `frontend/lib/api.ts`
- Modify: `backend/config/cors.php`

- [ ] **Step 1: Use relative API path in frontend**

```typescript
// frontend/lib/api.ts
const BASE_URL = '/api'
// ... rest of file
```

- [ ] **Step 2: Update CORS for unified domain**

Ensure Laravel allows requests from the same origin.

```php
// backend/config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'], // In unified deployment, origin is same as backend
```

- [ ] **Step 3: Commit**

```bash
git add frontend/lib/api.ts backend/config/cors.php
git commit -m "chore: use relative API paths for unified deployment"
```

---

### Task 3: Orchestration Configurations (Nginx, Supervisor, Entrypoint)

**Files:**
- Create: `docker/nginx.conf`
- Create: `docker/supervisord.conf`
- Create: `docker/entrypoint.sh`

- [ ] **Step 1: Create Nginx config**

```nginx
server {
    listen 80;
    root /var/www/html/public_frontend;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ ^/(api|sanctum) {
        root /var/www/html/public;
        try_files $uri /index.php?$query_string;
        
        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }
}
```

- [ ] **Step 2: Create Supervisor config**

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm83 -F
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-worker]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

- [ ] **Step 3: Create Dynamic Entrypoint**

```bash
#!/bin/sh

# Set worker count
WORKER_COUNT=${QUEUE_WORKER_COUNT:-1}
sed -i "s/numprocs=1/numprocs=$WORKER_COUNT/" /etc/supervisor/conf.d/supervisord.conf

# Run migrations (optional but recommended for monolith)
php /var/www/html/artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

- [ ] **Step 4: Commit**

```bash
git add docker/
git commit -m "infra: add nginx, supervisor, and entrypoint configs"
```

---

### Task 4: The Unified Dockerfile

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
RUN apk add --no-cache nginx supervisor
WORKDIR /var/www/html

# Copy Frontend
COPY --from=frontend-build /app/out /var/www/html/public_frontend

# Copy Backend
COPY --from=backend-build /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache

# Configs
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
```

- [ ] **Step 2: Commit**

```bash
git add Dockerfile
git commit -m "infra: add unified multi-stage Dockerfile"
```
