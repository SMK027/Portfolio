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

# Lien symbolique storage → public/storage (une seule fois)
if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction > /dev/null 2>&1 || true
fi

# Attendre que la base de données soit joignable (serveur local ou centralisé).
# Code de sortie du test : 0 = connecté, 1 = réessayer (serveur pas encore prêt),
# 2 = erreur définitive (identifiants, base inexistante, droits) : inutile d'attendre.
if [ "${DB_CONNECTION:-mysql}" = "mysql" ] || [ "${DB_CONNECTION}" = "mariadb" ]; then
    echo "[entrypoint] Attente de la base de données ${DB_HOST}:${DB_PORT:-3306}..."
    timeout="${DB_WAIT_TIMEOUT:-60}"
    elapsed=0
    while true; do
        status=0
        php -r '
            try {
                new PDO(
                    sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 3306, getenv("DB_DATABASE")),
                    getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]
                );
            } catch (PDOException $e) {
                fwrite(STDERR, $e->getMessage() . PHP_EOL);
                $code = (int) ($e->errorInfo[1] ?? 0);
                if ($code === 0 && preg_match("/\[(\d{4})\]/", $e->getMessage(), $m)) { $code = (int) $m[1]; }
                // 1044 : droits refusés sur la base, 1045 : identifiants refusés, 1049 : base inexistante
                exit(in_array($code, [1044, 1045, 1049], true) ? 2 : 1);
            }
        ' 2>/tmp/db-wait.log || status=$?

        [ "$status" -eq 0 ] && break

        if [ "$status" -eq 2 ]; then
            echo "[entrypoint] ERREUR : connexion refusée par ${DB_HOST} : $(tail -1 /tmp/db-wait.log)"
            case "$(cat /tmp/db-wait.log)" in
                *1045*) echo "[entrypoint] → Identifiants refusés. Vérifiez DB_USERNAME / DB_PASSWORD et que l'utilisateur '${DB_USERNAME}' existe pour l'hôte '%' (les conteneurs se connectent depuis une IP du réseau Docker, pas depuis localhost)." ;;
                *1049*) echo "[entrypoint] → La base '${DB_DATABASE}' n'existe pas sur ${DB_HOST} : créez-la (CREATE DATABASE)." ;;
                *1044*) echo "[entrypoint] → La base '${DB_DATABASE}' n'existe pas, ou l'utilisateur '${DB_USERNAME}' n'a pas de droits dessus : CREATE DATABASE puis GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%';" ;;
            esac
            # Pause avant de quitter, pour ne pas saturer les logs lors des redémarrages automatiques.
            sleep 10
            exit 1
        fi

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

# Base de pays des statistiques (DB-IP Lite) : téléchargée en arrière-plan si absente
if [ ! -f storage/app/geoip/country.mmdb ]; then
    (su -s /bin/sh www-data -c "php artisan portfolio:geoip-update" > /dev/null 2>&1 &)
fi

# Configurer le scheduler Laravel (cron)
touch /var/log/laravel-scheduler.log
chown www-data:www-data /var/log/laravel-scheduler.log
printf "* * * * * www-data /usr/local/bin/php /var/www/html/artisan schedule:run >> /var/log/laravel-scheduler.log 2>&1\n" \
    > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
cron
echo "[entrypoint] Scheduler Laravel démarré."

# File d'attente (e-mails envoyés en arrière-plan) : relancée automatiquement si elle s'arrête.
# En développement, queue:listen relit le code à chaque tâche ; en production, queue:work
# est redémarré toutes les heures (--max-time) pour libérer la mémoire.
if [ "${QUEUE_CONNECTION:-database}" != "sync" ]; then
    if [ "$APP_ENV" = "production" ]; then
        queue_cmd="php artisan queue:work --sleep=3 --tries=3 --max-time=3600"
    else
        queue_cmd="php artisan queue:listen --sleep=3 --tries=3"
    fi
    touch /var/log/laravel-queue.log
    chown www-data:www-data /var/log/laravel-queue.log
    (while true; do su -s /bin/sh www-data -c "cd /var/www/html && $queue_cmd" >> /var/log/laravel-queue.log 2>&1; sleep 5; done) &
    echo "[entrypoint] File d'attente démarrée."
fi

# Démarrer Apache
exec apache2-foreground
