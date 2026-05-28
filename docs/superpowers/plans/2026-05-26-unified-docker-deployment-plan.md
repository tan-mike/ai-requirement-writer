# Unified Docker Deployment Implementation Plan (Next.js Standalone Mode)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single Docker image containing the Next.js standalone frontend, Laravel backend, and configurable queue workers, served via Nginx.

**Architecture:** Multi-stage Docker build producing an Alpine-based image running Nginx, Node.js, PHP-FPM, and Supervisor.

**Tech Stack:** Docker, Nginx, Node.js, PHP 8.3-FPM, Supervisor.

---

### Task 1: Frontend Standalone Configuration

**Files:**
- Modify: `frontend/next.config.ts`

- [ ] **Step 1: Update Next.js config**

Add `output: 'standalone'`.

```typescript
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
};

export default nextConfig;
```

- [ ] **Step 2: Verify build command locally**

Run: `cd frontend && npm run build`
Expected: `.next/standalone` directory created.

- [ ] **Step 3: Commit**

```bash
git add frontend/next.config.ts
git commit -m "chore(frontend): enable standalone output for docker deployment"
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
'allowed_origins' => ['*'], 
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
    
    # Static assets (Next.js)
    location /_next/static {
        alias /var/www/html/frontend/.next/static;
        expires 365d;
        access_log off;
    }

    # Public files (Next.js)
    location /static {
        alias /var/www/html/frontend/public;
        expires 365d;
        access_log off;
    }

    # API Proxy
    location ~ ^/(api|sanctum) {
        root /var/www/html/backend/public;
        try_files $uri /index.php?$query_string;
        
        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }

    # Frontend Proxy (Next.js Standalone)
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
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

[program:next-app]
command=node /var/www/html/frontend/server.js
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
environment=NODE_ENV=production,PORT=3000

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
command=php /var/www/html/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
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

# Run migrations
php /var/www/html/backend/artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

- [ ] **Step 4: Commit**

```bash
git add docker/
git commit -m "infra: add nginx, supervisor, and entrypoint configs for standalone mode"
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
RUN apk add --no-cache nginx supervisor nodejs
WORKDIR /var/www/html

# Copy Frontend Standalone
COPY --from=frontend-build /app/.next/standalone /var/www/html/frontend
COPY --from=frontend-build /app/.next/static /var/www/html/frontend/.next/static
COPY --from=frontend-build /app/public /var/www/html/frontend/public

# Copy Backend
COPY --from=backend-build /var/www/html /var/www/html/backend
RUN chown -R www-data:www-data /var/www/html/backend/storage /var/www/html/backend/bootstrap/cache

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
git commit -m "infra: add unified multi-stage Dockerfile for standalone mode"
```
