# 1) Compilar assets del frontend (Vite + Tailwind)
FROM node:22-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


# 2) Imagen final: PHP + Apache
FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Render inyecta PORT (10000 por defecto). Apache lo lee en tiempo de ejecución.
ENV PORT=10000 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

RUN a2enmod rewrite \
    && sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

# Al arrancar: cachea config/rutas/vistas, corre migraciones y levanta Apache.
# Las migraciones usan la conexión directa (sin pooler) de Neon.
# Si no se definió APP_URL, usa la URL pública que provee Render.
CMD APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}" php artisan optimize \
    && php artisan migrate --force --database=pgsql_direct \
    && chown -R www-data:www-data storage bootstrap/cache \
    && exec apache2-foreground
