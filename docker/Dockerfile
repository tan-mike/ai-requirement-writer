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
# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY backend/ .
RUN composer update --no-dev --optimize-autoloader

# Stage 3: Runtime
FROM php:8.3-fpm-alpine
# Install runtime dependencies and PDO MySQL
RUN apk add --no-cache nginx supervisor nodejs
RUN docker-php-ext-install pdo_mysql
# Create supervisor log directory
RUN mkdir -p /var/log/supervisor
WORKDIR /var/www/html

# Copy Frontend Standalone
COPY --from=frontend-build /app/.next/standalone /var/www/html/frontend
COPY --from=frontend-build /app/.next/static /var/www/html/frontend/.next/static
COPY --from=frontend-build /app/public /var/www/html/frontend/public

# Fix for Next.js standalone mode: it often expects files in a nested directory named after the build WORKDIR
RUN mkdir -p /var/www/html/frontend/app/.next &&     ln -s /var/www/html/frontend/.next/static /var/www/html/frontend/app/.next/static &&     ln -s /var/www/html/frontend/public /var/www/html/frontend/app/public

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
