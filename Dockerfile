# ==========================================
#          BASE IMAGE PHP 7.4 FPM
# ==========================================
FROM php:7.4-fpm

# ==========================================
#      INSTALAR DEPENDENCIAS DEL SISTEMA
# ==========================================
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install zip pdo pdo_mysql

# ==========================================
#          INSTALAR COMPOSER
# ==========================================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ==========================================
#         DIRECTORIO DE TRABAJO
# ==========================================
WORKDIR /var/www/html

# ==========================================
#          COPIAR PROYECTO COMPLETO
# ==========================================
COPY . .

# ==========================================
#      INSTALAR DEPENDENCIAS DE LARAVEL
# ==========================================
RUN composer install --no-dev --prefer-dist --optimize-autoloader

# ==========================================
#              PERMISOS
# ==========================================
RUN chown -R www-data:www-data storage bootstrap/cache

# ==========================================
#      GENERAR APP_KEY (IGNORAR SI EXISTE)
# ==========================================
RUN php artisan key:generate || true

# ==========================================
#         PUERTO DE LARAVEL
# ==========================================
EXPOSE 8000

# ==========================================
#         COMANDO DE INICIO
# ==========================================
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
