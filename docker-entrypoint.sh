#!/bin/bash
set -e

# Fixer les permissions Laravel
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# APP_KEY : obligatoire en production (l'image n'embarque pas de fichier .env),
# générée automatiquement en développement.
if [ -z "$APP_KEY" ]; then
    if [ "$APP_ENV" = "production" ]; then
        echo "[entrypoint] ERREUR : APP_KEY est vide. Générez-la (php artisan key:generate --show) et ajoutez-la au .env du serveur."
        exit 1
    fi
    php artisan key:generate --force
    echo "[entrypoint] APP_KEY généré."
fi

# Lien symbolique storage → public/storage
php artisan storage:link --no-interaction 2>/dev/null || true

# Attendre que la base de données soit joignable (serveur local ou centralisé)
if [ "${DB_CONNECTION:-mysql}" = "mysql" ] || [ "${DB_CONNECTION}" = "mariadb" ]; then
    echo "[entrypoint] Attente de la base de données ${DB_HOST}:${DB_PORT:-3306}..."
    timeout="${DB_WAIT_TIMEOUT:-60}"
    elapsed=0
    until php -r '
        try {
            new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 3306, getenv("DB_DATABASE")),
                getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]
            );
        } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
    ' 2>/tmp/db-wait.log; do
        if [ "$elapsed" -ge "$timeout" ]; then
            echo "[entrypoint] ERREUR : base injoignable après ${timeout}s : $(tail -1 /tmp/db-wait.log)"
            exit 1
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done
    echo "[entrypoint] Base de données joignable."
fi

# Exécuter les migrations
# (tant que cette étape n'est pas terminée, Apache n'écoute pas encore :
#  Traefik répond « Bad Gateway » — consultez les logs du conteneur)
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
