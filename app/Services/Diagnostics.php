<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnostic en temps réel du site (super-administrateurs) : chaque vérification renvoie
 * un état (ok, warning, error), une valeur courte et un détail facultatif.
 */
class Diagnostics
{
    /**
     * Battement du planificateur, enregistré chaque minute (routes/console.php) sous forme
     * d'horodatage entier : le cache refuse de désérialiser les objets (serializable_classes).
     */
    public const SCHEDULER_HEARTBEAT = 'diagnostics.scheduler';

    public function __construct(
        protected SafeMailer $mailer,
        protected GoogleCalendar $google,
        protected Sandbox $sandbox,
        protected Maintenance $maintenance,
    ) {}

    /** @return array{checked_at: string, overall: string, groups: array<string, list<array<string, mixed>>>, errors: list<array<string, string>>, system: array<string, mixed>} */
    public function run(): array
    {
        $groups = [
            'Application' => [$this->environment(), $this->maintenanceCheck()],
            'Services'    => [$this->database(), $this->cache(), $this->queue(), $this->scheduler()],
            'Stockage'    => [$this->disk(), $this->writable()],
            'Intégrations' => [$this->mail(), $this->googleCalendar(), $this->sandboxCheck()],
        ];
        $states = collect($groups)->flatten(1)->pluck('status');

        return [
            'checked_at' => now()->toIso8601String(),
            'overall'    => $states->contains('error') ? 'error' : ($states->contains('warning') ? 'warning' : 'ok'),
            'groups'     => $groups,
            'errors'     => $this->recentLogErrors(),
            'system'     => $this->system(),
        ];
    }

    /** @return array<string, mixed> */
    protected function check(string $label, string $status, string $value, ?string $detail = null): array
    {
        return compact('label', 'status', 'value', 'detail');
    }

    /** Durée d'un traitement en millisecondes. @return array{0: mixed, 1: float} */
    protected function timed(callable $callback): array
    {
        $start = hrtime(true);
        $result = $callback();

        return [$result, round((hrtime(true) - $start) / 1e6, 1)];
    }

    protected function environment(): array
    {
        $risky = app()->isProduction() && config('app.debug');

        return $this->check('Environnement', $risky ? 'error' : 'ok', app()->environment().(config('app.debug') ? ' · debug' : ''),
            $risky ? 'APP_DEBUG est actif en production : les erreurs détaillées sont visibles des visiteurs.' : 'PHP '.PHP_VERSION.' · Laravel '.app()->version());
    }

    protected function maintenanceCheck(): array
    {
        $active = $this->maintenance->isActive();

        return $this->check('Maintenance', $active ? 'warning' : 'ok', $active ? 'Active' : 'Inactive',
            $active ? 'Les pages publiques sont inaccessibles aux visiteurs.' : null);
    }

    protected function database(): array
    {
        try {
            [, $ms] = $this->timed(fn () => DB::select('select 1'));

            return $this->check('Base de données', $ms > 200 ? 'warning' : 'ok', "{$ms} ms", DB::connection()->getDriverName().' · '.DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            return $this->check('Base de données', 'error', 'Injoignable', $e->getMessage());
        }
    }

    protected function cache(): array
    {
        try {
            $token = bin2hex(random_bytes(4));
            [$read, $ms] = $this->timed(function () use ($token) {
                Cache::put('diagnostics.probe', $token, 60);

                return Cache::get('diagnostics.probe');
            });

            return $read === $token
                ? $this->check('Cache', $ms > 200 ? 'warning' : 'ok', "{$ms} ms", 'Pilote '.config('cache.default'))
                : $this->check('Cache', 'error', 'Lecture incohérente', 'La valeur écrite n\'a pas été relue.');
        } catch (Throwable $e) {
            return $this->check('Cache', 'error', 'Indisponible', $e->getMessage());
        }
    }

    protected function queue(): array
    {
        if (config('queue.default') !== 'database') {
            return $this->check('File d\'attente', 'ok', config('queue.default'), 'Tâches traitées hors base de données.');
        }

        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $late = $oldest && now()->timestamp - (int) $oldest > 300;

            return $this->check('File d\'attente', $failed || $late ? 'warning' : 'ok', "{$pending} en attente · {$failed} en échec", match (true) {
                $late   => 'Une tâche attend depuis plus de 5 minutes : le worker (queue:work) tourne-t-il ?',
                $failed > 0 => 'Des tâches ont échoué : php artisan queue:failed pour le détail.',
                default => null,
            });
        } catch (Throwable $e) {
            return $this->check('File d\'attente', 'error', 'Illisible', $e->getMessage());
        }
    }

    protected function scheduler(): array
    {
        $last = Cache::get(self::SCHEDULER_HEARTBEAT);
        if (! is_int($last)) {
            return $this->check('Planificateur', 'warning', 'Jamais vu', 'Aucun passage enregistré : la tâche cron (schedule:run) est-elle en place ?');
        }

        $age = max(0, now()->timestamp - $last);

        return $this->check('Planificateur', $age > 180 ? 'error' : 'ok', $age < 60 ? "il y a {$age} s" : 'il y a '.intdiv($age, 60).' min',
            $age > 180 ? 'Le planificateur ne passe plus : rappels, synchronisations et purges sont à l\'arrêt.' : null);
    }

    protected function disk(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if (! $free || ! $total) {
            return $this->check('Espace disque', 'warning', 'Inconnu');
        }

        $percent = round($free / $total * 100);

        return $this->check('Espace disque', $percent < 5 ? 'error' : ($percent < 15 ? 'warning' : 'ok'),
            $this->bytes($free).' libres ('.$percent.' %)', 'Sur '.$this->bytes($total));
    }

    protected function writable(): array
    {
        $paths = ['storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'];
        $blocked = array_values(array_filter($paths, fn ($path) => File::isDirectory(base_path($path)) && ! is_writable(base_path($path))));

        return $this->check('Droits d\'écriture', $blocked ? 'error' : 'ok', $blocked ? count($blocked).' dossier(s) bloqué(s)' : 'OK',
            $blocked ? implode(', ', $blocked) : null);
    }

    protected function mail(): array
    {
        if ($problem = $this->mailer->configurationProblem()) {
            return $this->check('E-mails', 'error', 'Mal configuré', $problem);
        }
        $error = $this->mailer->lastError();

        return $this->check('E-mails', $error ? 'warning' : 'ok', config('mail.default'),
            $error ? 'Dernier échec : '.$error['message'] : null);
    }

    protected function googleCalendar(): array
    {
        if (! $this->google->isConnected()) {
            return $this->check('Google Agenda', 'ok', 'Non connecté');
        }
        $status = $this->google->status();

        return $this->check('Google Agenda', $status['error'] ? 'warning' : 'ok',
            $status['synced_at'] ? 'Synchronisé '.\Illuminate\Support\Carbon::parse($status['synced_at'])->diffForHumans() : 'Jamais synchronisé',
            $status['error']);
    }

    protected function sandboxCheck(): array
    {
        if (! $this->sandbox->isConfigured()) {
            return $this->check('Machine SSH', 'ok', 'Non configurée');
        }

        // Appel distant : résultat gardé 30 s pour ne pas solliciter l'agent à chaque rafraîchissement.
        return Cache::remember('diagnostics.sandbox', 30, function () {
            try {
                [, $ms] = $this->timed(fn () => $this->sandbox->ping());

                return $this->check('Machine SSH', 'ok', "Agent joignable ({$ms} ms)");
            } catch (Throwable $e) {
                return $this->check('Machine SSH', 'error', 'Agent injoignable', $e->getMessage());
            }
        });
    }

    /** Dernières erreurs du journal Laravel (fichier le plus récent). @return list<array<string, string>> */
    protected function recentLogErrors(int $limit = 8): array
    {
        $file = collect(File::glob(storage_path('logs/*.log')))->sortByDesc(fn ($path) => filemtime($path))->first();
        if (! $file || ! is_readable($file)) {
            return [];
        }

        // Fin du fichier seulement (512 Ko au plus).
        $handle = fopen($file, 'r');
        fseek($handle, -min(filesize($file), 512 * 1024), SEEK_END);
        $tail = stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d\d-\d\d[ T][\d:]+)[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $tail, $matches, PREG_SET_ORDER);

        return collect($matches)->reverse()->take($limit)->map(fn ($match) => [
            'at'      => $match[1],
            'level'   => $match[2],
            'message' => \Illuminate\Support\Str::limit(trim($match[3]), 300),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    protected function system(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return [
            'Heure du serveur' => now()->format('d/m/Y H:i:s').' ('.config('app.timezone').')',
            'Charge (1/5/15 min)' => $load ? implode(' / ', array_map(fn ($value) => number_format($value, 2), $load)) : '—',
            'Mémoire PHP' => $this->bytes(memory_get_usage(true)).' (limite '.ini_get('memory_limit').')',
            'Version PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
        ];
    }

    protected function bytes(float|int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i > 1 ? 1 : 0, ',', ' ').' '.$units[$i];
    }
}
