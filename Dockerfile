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
