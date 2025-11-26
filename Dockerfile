FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \        # <--- NECESARIO
    zip \
    unzip \
    git \
    curl

# Instalar extensiones PHP necesarias para Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip
#                                                ^^^^^^^^
#                           AGREGA AQUÍ        (ext-zip)

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Crear carpeta app
WORKDIR /var/www

# Copiar archivos de Laravel
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --prefer-dist --optimize-autoloader

# Dar permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

CMD php artisan serve --host=0.0.0.0 --port=10000
