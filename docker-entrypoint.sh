#!/bin/bash
set -e

# Fixer les permissions Laravel
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Générer APP_KEY si absente
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
    echo "[entrypoint] APP_KEY généré."
fi

# Lien symbolique storage → public/storage
php artisan storage:link --no-interaction 2>/dev/null || true

# Exécuter les migrations
echo "[entrypoint] Exécution des migrations..."
php artisan migrate --force
echo "[entrypoint] Migrations terminées."

# Crée le premier administrateur si aucun n'existe
php artisan db:seed --class=AdminSeeder --force

# Optimiser les caches (config, routes, vues) uniquement en production :
# en développement, le code est monté en volume et doit être relu à chaque requête.
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    echo "[entrypoint] Caches reconstruits."
else
    php artisan optimize:clear > /dev/null
    echo "[entrypoint] Caches vidés (environnement $APP_ENV)."
fi

# Configurer le scheduler Laravel (cron)
touch /var/log/laravel-scheduler.log
chown www-data:www-data /var/log/laravel-scheduler.log
printf "* * * * * www-data /usr/local/bin/php /var/www/html/artisan schedule:run >> /var/log/laravel-scheduler.log 2>&1\n" \
    > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
cron
echo "[entrypoint] Scheduler Laravel démarré."

# Démarrer Apache
exec apache2-foreground
