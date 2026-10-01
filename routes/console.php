<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Statistiques de visite : données de plus de 13 mois supprimées chaque nuit.
Schedule::call(fn () => app(\App\Services\SiteStatistics::class)->prune())->dailyAt('03:30')->name('statistiques:purge');

// Dépôts GitHub des projets : rafraîchis avant l'expiration du cache (12 h).
Schedule::call(fn () => app(\App\Services\GithubRepositories::class)->refreshAll())->everySixHours()->name('github:rafraichir');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
