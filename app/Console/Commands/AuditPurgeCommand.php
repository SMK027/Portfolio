<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/** Supprime les entrées du journal d'activité d'une période (une trace de la purge est conservée). */
class AuditPurgeCommand extends Command
{
    protected $signature = 'portfolio:audit-purge
        {--du= : Premier jour inclus (AAAA-MM-JJ)}
        {--au= : Dernier jour inclus (AAAA-MM-JJ)}
        {--force : Supprime sans demander de confirmation}';

    protected $description = 'Supprime les entrées du journal d\'activité sur une période donnée';

    public function handle(): int
    {
        try {
            $from = Carbon::createFromFormat('!Y-m-d', (string) $this->option('du'));
            $to = Carbon::createFromFormat('!Y-m-d', (string) $this->option('au'));
        } catch (Throwable) {
            $from = $to = false;
        }

        if (! $from || ! $to) {
            $this->error('Indiquez la période : php artisan portfolio:audit-purge --du=2026-10-01 --au=2026-10-31');

            return self::FAILURE;
        }
        if ($from->gt($to) || $to->isAfter(today())) {
            $this->error('Période invalide : le début doit précéder la fin, qui ne peut pas dépasser aujourd\'hui.');

            return self::FAILURE;
        }

        $count = AuditLog::period($from, $to)->count();
        $range = 'du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y');
        if ($count === 0) {
            $this->info("Aucune entrée {$range}.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Supprimer définitivement {$count} entrée(s) {$range} ?")) {
            $this->warn('Purge annulée.');

            return self::SUCCESS;
        }

        $this->info(AuditLog::purgePeriod($from, $to)." entrée(s) supprimée(s) {$range}.");

        return self::SUCCESS;
    }
}
