<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Statistiques de visite : données de plus de 13 mois supprimées chaque nuit.
Schedule::call(fn () => app(\App\Services\SiteStatistics::class)->prune())->dailyAt('03:30')->name('statistiques:purge');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
