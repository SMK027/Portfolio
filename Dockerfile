FROM php:8.3-apache

# Dépendances système
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip bcmath pcntl exif \
    && a2enmod rewrite remoteip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configuration mod_remoteip (IP réelle derrière proxy)
RUN printf '<IfModule remoteip_module>\n\
    RemoteIPHeader X-Forwarded-For\n\
    RemoteIPInternalProxy 172.16.0.0/12\n\
    RemoteIPInternalProxy 10.0.0.0/8\n\
    RemoteIPInternalProxy 192.168.0.0/16\n\
    RemoteIPInternalProxy 127.0.0.1\n\
</IfModule>\n' > /etc/apache2/conf-available/remoteip.conf \
    && a2enconf remoteip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Document root → public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configuration PHP (production)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN printf "upload_max_filesize = 10M\npost_max_size = 12M\nmemory_limit = 256M\n" \
    > "$PHP_INI_DIR/conf.d/custom.ini"

# Répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers de l'application
COPY . .

# Installer les dépendances PHP (sans dev)
RUN composer install --no-dev --optimize-autoloader

# Permissions Laravel (storage + bootstrap/cache)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80
