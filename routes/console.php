<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Statistiques de visite : données de plus de 13 mois supprimées chaque nuit.
Schedule::call(fn () => app(\App\Services\SiteStatistics::class)->prune())->dailyAt('03:30')->name('statistiques:purge');

// Téléchargements du CV par code : IP effacées après 3 mois.
Schedule::call(fn () => \App\Models\CvAccessCodeUse::prune())->dailyAt('03:35')->name('cv:purge-ip');

// Machine SSH : échecs de connexion transmis au bannissement automatique, puis état
// réappliqué (adresses bannies, expirations des comptes), chaque minute.
Schedule::call(function () {
    $sandbox = app(\App\Services\Sandbox::class);
    if ($sandbox->isConfigured()) {
        $sandbox->processAuthFailures();
        $sandbox->sync();
        $sandbox->monitor();
    }
})->everyMinute()->name('machine-ssh:synchroniser')->withoutOverlapping();
// … sauvegarde automatique, puis réinitialisation planifiée et rotation du mot de passe invité.
Schedule::call(fn () => app(\App\Services\Sandbox::class)->automaticBackup())->dailyAt('04:15')->name('machine-ssh:sauvegarde');
Schedule::call(fn () => app(\App\Services\Sandbox::class)->scheduledReset())->dailyAt('04:30')->name('machine-ssh:reinitialisation');
Schedule::call(fn () => app(\App\Services\Sandbox::class)->rotateGuestPassword())->dailyAt('04:45')->name('machine-ssh:mot-de-passe-invite');
Schedule::call(fn () => \App\Models\SandboxConnection::prune())->dailyAt('03:45')->name('machine-ssh:purge');

// Rappels des rendez-vous confirmés (la veille et 1 h avant).
Schedule::call(fn () => app(\App\Services\AppointmentReminders::class)->send())->everyFiveMinutes()->name('rendez-vous:rappels');

// Google Agenda : périodes occupées importées (elles bloquent les créneaux de rendez-vous).
Schedule::call(fn () => app(\App\Services\GoogleCalendar::class)->syncBusy())->everyTenMinutes()->name('rendez-vous:google-agenda');

// Comptes du personnel arrivés à leur date de désactivation programmée.
// Battement du planificateur, contrôlé par la page de diagnostic.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever(\App\Services\Diagnostics::SCHEDULER_HEARTBEAT, now()))->everyMinute()->name('diagnostic:battement');

Schedule::call(fn () => \App\Models\User::deactivateExpired())->everyMinute()->name('comptes:desactivation');
// … et avertis quelques jours avant (STAFF_DEACTIVATION_WARNING_DAYS).
Schedule::call(fn () => \App\Models\User::warnUpcomingDeactivations())->hourly()->name('comptes:avertissement');

// Bannissements d'adresses IP terminés depuis plus de 3 mois : supprimés chaque nuit.
Schedule::call(fn () => app(\App\Services\LoginBan::class)->prune())->dailyAt('03:40')->name('ip-bannies:purge');

// Formulaire de contact : codes de vérification de plus de 2 jours supprimés.
Schedule::call(fn () => \App\Models\ContactVerification::prune())->dailyAt('03:55')->name('contact:purge-verifications');

// Journal d'activité : archivé après 1 mois, supprimé définitivement après 3 mois.
Schedule::call(fn () => \App\Models\AuditLog::applyRetention())->dailyAt('03:50')->name('journal:conservation');

// Dépôts GitHub des projets : rafraîchis avant l'expiration du cache (12 h).
Schedule::call(fn () => app(\App\Services\GithubRepositories::class)->refreshAll())->everySixHours()->name('github:rafraichir');

// Bases de géolocalisation (DB-IP City/Country Lite) : mise à jour mensuelle, et au premier besoin.
Artisan::command('portfolio:geoip-update', function () {
    $ok = app(\App\Services\GeoIp::class)->update();
    $ok ? $this->info('Bases de géolocalisation mises à jour : '.dirname(\App\Services\GeoIp::path())) : $this->error('Téléchargement impossible (voir les logs).');
})->purpose('Télécharge les bases IP → pays / ville (DB-IP Lite, CC BY 4.0)');
Schedule::command('portfolio:geoip-update')->monthlyOn(3, '04:00')->name('geoip:update');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
