<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Statistiques de visite : données de plus de 13 mois supprimées chaque nuit.
Schedule::call(fn () => app(\App\Services\SiteStatistics::class)->prune())->dailyAt('03:30')->name('statistiques:purge');

// Rappels des rendez-vous confirmés (la veille et 1 h avant).
Schedule::call(fn () => app(\App\Services\AppointmentReminders::class)->send())->everyFiveMinutes()->name('rendez-vous:rappels');

// Comptes du personnel arrivés à leur date de désactivation programmée.
Schedule::call(fn () => \App\Models\User::deactivateExpired())->everyMinute()->name('comptes:desactivation');
// … et avertis quelques jours avant (STAFF_DEACTIVATION_WARNING_DAYS).
Schedule::call(fn () => \App\Models\User::warnUpcomingDeactivations())->hourly()->name('comptes:avertissement');

// Bannissements d'adresses IP terminés depuis plus de 3 mois : supprimés chaque nuit.
Schedule::call(fn () => app(\App\Services\LoginBan::class)->prune())->dailyAt('03:40')->name('ip-bannies:purge');

// Dépôts GitHub des projets : rafraîchis avant l'expiration du cache (12 h).
Schedule::call(fn () => app(\App\Services\GithubRepositories::class)->refreshAll())->everySixHours()->name('github:rafraichir');

// Base de pays (DB-IP Lite) : mise à jour mensuelle, et au premier besoin.
Artisan::command('portfolio:geoip-update', function () {
    $ok = app(\App\Services\GeoIp::class)->update();
    $ok ? $this->info('Base de pays mise à jour : '.\App\Services\GeoIp::path()) : $this->error('Téléchargement impossible (voir les logs).');
})->purpose('Télécharge la base IP → pays (DB-IP Lite, CC BY 4.0)');
Schedule::command('portfolio:geoip-update')->monthlyOn(3, '04:00')->name('geoip:update');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
