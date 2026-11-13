<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Comparaison de la configuration réelle avec le modèle .env.example.
 *
 * La configuration réelle est le fichier .env (développement) complété par les
 * variables d'environnement du processus (conteneur de production, où le .env
 * n'existe pas). Contrôles :
 * - variables critiques absentes ou vides (erreur) ;
 * - variables du modèle non définies (avertissement : la valeur par défaut s'applique) ;
 * - règles de production (APP_DEBUG, HTTPS, reCAPTCHA, mots de passe d'exemple…) ;
 * - variables du .env inconnues (faute de frappe probable) ;
 * - variables non transmises au conteneur par docker-compose.yml.
 *
 * Utilisé par la commande « portfolio:env-check » et par la page de diagnostic.
 */
class EnvironmentCheck
{
    /** Variables indispensables au fonctionnement du site : clé => raison. */
    public const CRITICAL = [
        'APP_KEY'           => 'chiffrement des sessions, cookies et secrets enregistrés',
        'APP_ENV'           => 'choix du comportement (production ou développement)',
        'APP_URL'           => 'liens absolus des e-mails, du sitemap et des redirections',
        'DB_CONNECTION'     => 'connexion à la base de données',
        'DB_HOST'           => 'connexion à la base de données',
        'DB_DATABASE'       => 'connexion à la base de données',
        'DB_USERNAME'       => 'connexion à la base de données',
        'DB_PASSWORD'       => 'connexion à la base de données',
        'MAIL_MAILER'       => 'envoi des e-mails (contact, rendez-vous, comptes)',
        'MAIL_FROM_ADDRESS' => 'expéditeur des e-mails',
    ];

    /** Critiques en production uniquement. */
    public const CRITICAL_IN_PRODUCTION = [
        'MAIL_HOST'            => 'serveur d\'envoi des e-mails',
        'RECAPTCHA_SITE_KEY'   => 'sans clés reCAPTCHA, les formulaires publics sont refusés en production',
        'RECAPTCHA_SECRET_KEY' => 'sans clés reCAPTCHA, les formulaires publics sont refusés en production',
    ];

    /** Variables propres à Docker Compose ou à la compilation, jamais lues par l'application. */
    public const DOCKER_ONLY = [
        'APP_PORT', 'APP_DOMAIN', 'COMPOSE_PROJECT_NAME', 'DB_FORWARD_PORT', 'DB_ROOT_PASSWORD', 'DB_WAIT_TIMEOUT',
        'PMA_PORT', 'MAILPIT_UI_PORT', 'MAILPIT_SMTP_PORT', 'VITE_APP_NAME', 'PHP_CLI_SERVER_WORKERS',
    ];

    /** Variables standard de Laravel : leur valeur par défaut convient le plus souvent. */
    protected const FRAMEWORK = '/^(APP_(FAKER_LOCALE|MAINTENANCE_\w+)|BCRYPT_ROUNDS|LOG_\w+|SESSION_\w+|BROADCAST_\w+|FILESYSTEM_\w+|CACHE_PREFIX|MEMCACHED_\w+|REDIS_\w+|AWS_\w+)$/';

    /** Valeurs d'exemple qui ne doivent jamais servir en production. */
    protected const EXAMPLE_SECRETS = ['DB_PASSWORD', 'DB_ROOT_PASSWORD', 'SANDBOX_API_TOKEN'];

    /**
     * @param  array<string, string>|null  $environment  variables du processus (null : environnement réel)
     */
    public function __construct(protected ?string $basePath = null, protected ?array $environment = null)
    {
        $this->basePath ??= base_path();
    }

    /**
     * @return array{production: bool, issues: list<array{level: string, key: string, message: string}>, counts: array<string, int>}
     */
    public function run(?bool $production = null): array
    {
        $example = $this->parseFile($this->basePath.'/.env.example');
        $file = $this->parseFile($this->basePath.'/.env');
        $actual = fn (string $key) => $this->value($key, $file['values']);
        $production ??= $actual('APP_ENV') === 'production';
        $issues = [];
        $add = function (string $level, string $key, string $message) use (&$issues) {
            $issues[] = compact('level', 'key', 'message');
        };

        if ($example['values'] === [] && $example['optional'] === []) {
            $add('error', '.env.example', 'Modèle introuvable ou vide : comparaison impossible.');
        }

        // Variables critiques.
        $critical = self::CRITICAL + ($production ? self::CRITICAL_IN_PRODUCTION : []);
        foreach ($critical as $key => $reason) {
            if (blank($actual($key))) {
                $add('error', $key, 'Absente ou vide : '.$reason.'.');
            }
        }

        // Variables du modèle non définies (hors critiques, déjà signalées, et hors Docker).
        foreach (array_keys($example['values']) as $key) {
            if (! isset($critical[$key]) && ! in_array($key, self::DOCKER_ONLY, true) && $actual($key) === null) {
                // Valeur vide dans le modèle : réglage facultatif (intégration non utilisée).
                $add(blank($example['values'][$key]) ? 'info' : 'warning', $key, 'Absente de la configuration : la valeur par défaut du code s\'applique'
                    .(filled($example['values'][$key]) ? ' (modèle : '.Str::limit($example['values'][$key], 40).')' : '').'.');
            }
        }

        $this->checkValues($actual, $example['values'], $production, $add);

        // Variables du .env inconnues du modèle et du code : faute de frappe probable.
        $known = array_merge(array_keys($example['values']), $example['optional'], $this->referencedInCode());
        foreach (array_keys($file['values']) as $key) {
            if (! in_array($key, $known, true)) {
                $close = collect($known)->unique()->first(fn ($candidate) => levenshtein($key, $candidate) <= 2);
                $add('info', $key, 'Inconnue du modèle et du code'.($close ? " : vouliez-vous dire {$close} ?" : ' : variable inutile ?'));
            }
        }

        // Variables lues par l'application mais non transmises au conteneur de production.
        foreach ($this->notForwardedByCompose(array_keys($example['values'])) as $key) {
            $framework = (bool) preg_match(self::FRAMEWORK, $key);
            $add($framework ? 'info' : 'warning', $key, 'Non transmise au conteneur par docker-compose.yml : '
                .($framework ? 'valeur par défaut de Laravel en production.' : 'ignorée en production.'));
        }

        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, fn ($a, $b) => [$order[$a['level']], $a['key']] <=> [$order[$b['level']], $b['key']]);
        $counts = array_merge(['error' => 0, 'warning' => 0, 'info' => 0], array_count_values(array_column($issues, 'level')));

        return compact('production', 'issues', 'counts');
    }

    /** Contrôle des valeurs elles-mêmes. */
    protected function checkValues(\Closure $actual, array $example, bool $production, \Closure $add): void
    {
        $key = (string) $actual('APP_KEY');
        if ($key !== '' && strlen(str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7), true) : $key) !== 32) {
            $add('error', 'APP_KEY', 'Clé invalide (32 octets attendus) : php artisan key:generate.');
        }

        $url = (string) $actual('APP_URL');
        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            $add('error', 'APP_URL', 'Adresse invalide : '.$url);
        }

        $email = (string) $actual('MAIL_FROM_ADDRESS');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $add('error', 'MAIL_FROM_ADDRESS', 'Adresse e-mail invalide : '.$email);
        }

        if (! $production) {
            return;
        }

        if (filter_var($actual('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
            $add('error', 'APP_DEBUG', 'Actif en production : les erreurs détaillées (et secrets) sont visibles des visiteurs.');
        }
        if ($url !== '' && ! str_starts_with($url, 'https://')) {
            $add('warning', 'APP_URL', 'N\'utilise pas HTTPS en production.');
        }
        if (in_array($actual('MAIL_MAILER'), ['log', 'array'], true)) {
            $add('warning', 'MAIL_MAILER', 'Pilote « '.$actual('MAIL_MAILER').' » en production : aucun e-mail n\'est réellement envoyé.');
        }
        if ($actual('LOG_LEVEL') === 'debug') {
            $add('info', 'LOG_LEVEL', 'Niveau « debug » en production : journaux volumineux.');
        }
        if (str_contains((string) $actual('MAIL_FROM_ADDRESS'), 'example.')) {
            $add('warning', 'MAIL_FROM_ADDRESS', 'Adresse d\'exemple en production.');
        }
        foreach (self::EXAMPLE_SECRETS as $secret) {
            if (filled($example[$secret] ?? null) && $actual($secret) === $example[$secret]) {
                $add('error', $secret, 'Valeur d\'exemple du modèle utilisée en production : changez-la.');
            }
        }
    }

    /**
     * Valeur effective : variable d'environnement du processus, sinon fichier .env.
     * null = non définie ; '' = définie mais vide.
     */
    protected function value(string $key, array $file): ?string
    {
        $sources = $this->environment !== null
            ? [$this->environment[$key] ?? null]
            : [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key) === false ? null : getenv($key)];
        foreach ($sources as $value) {
            if ($value !== null && ! is_array($value)) {
                return $this->clean((string) $value);
            }
        }

        return array_key_exists($key, $file) ? $file[$key] : null;
    }

    /** « null » et « (null) » valent une valeur vide, comme pour Laravel. */
    protected function clean(string $value): string
    {
        return in_array(strtolower($value), ['null', '(null)'], true) ? '' : $value;
    }

    /**
     * @return array{values: array<string, string>, optional: list<string>}  optional : variables commentées (« # CLE= »)
     */
    public function parseFile(string $path): array
    {
        $values = [];
        $optional = [];
        if (! is_readable($path)) {
            return compact('values', 'optional');
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*#\s*([A-Z][A-Z0-9_]*)=/', $line, $m)) {
                $optional[] = $m[1];
            } elseif (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                $value = trim($m[2]);
                if (preg_match('/^(["\'])(.*)\1/', $value, $quoted)) {
                    $value = $quoted[2];
                } else {
                    $value = trim(preg_replace('/\s+#.*$/', '', $value));
                }
                $values[$m[1]] = $this->clean($value);
            }
        }

        return compact('values', 'optional');
    }

    /** Variables lues par le code (env('…') dans config/, app/, routes/, bootstrap/). */
    protected function referencedInCode(): array
    {
        $keys = [];
        foreach (['config', 'app', 'routes', 'bootstrap'] as $dir) {
            if (! is_dir($this->basePath.'/'.$dir)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->basePath.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() === 'php' && preg_match_all("/env\\(\\s*'([A-Z0-9_]+)'/", (string) file_get_contents($file->getPathname()), $m)) {
                    array_push($keys, ...$m[1]);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Variables du modèle absentes de la section « environment » de docker-compose.yml
     * (production). Vide si le fichier est absent.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function notForwardedByCompose(array $keys): array
    {
        $path = $this->basePath.'/docker-compose.yml';
        if (! is_readable($path)) {
            return [];
        }

        preg_match_all('/^\s*-\s*([A-Z][A-Z0-9_]*)=/m', (string) file_get_contents($path), $m);
        $forwarded = $m[1];

        return array_values(array_filter($keys, fn ($key) => ! in_array($key, $forwarded, true) && ! in_array($key, self::DOCKER_ONLY, true)));
    }
}
