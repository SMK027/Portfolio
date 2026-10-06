<?php

namespace App\Console\Commands;

use App\Models\IpBan;
use App\Services\LoginBan;
use Illuminate\Console\Command;

/**
 * Liste ou lève les bannissements d'adresses IP (voir App\Services\LoginBan).
 * Recours si un administrateur a banni sa propre adresse.
 */
class IpBanCommand extends Command
{
    protected $signature = 'portfolio:ip-ban
        {ip? : Adresse IP dont lever le bannissement}
        {--lift : Lève le bannissement de l\'adresse indiquée}';

    protected $description = 'Liste les adresses IP bannies, ou lève un bannissement (--lift)';

    public function handle(LoginBan $bans): int
    {
        $ip = $this->argument('ip');

        if (! $this->option('lift')) {
            $active = IpBan::active()->latest()->get();
            if ($active->isEmpty()) {
                $this->info('Aucune adresse IP bannie.');

                return self::SUCCESS;
            }
            $this->table(['Adresse IP', 'Depuis', 'Jusqu\'au', 'Motif'], $active->map(fn (IpBan $ban) => [
                $ban->ip_address,
                $ban->created_at->format('d/m/Y H:i'),
                $ban->banned_until?->format('d/m/Y H:i') ?? 'sans date de fin',
                $ban->reason,
            ]));

            return self::SUCCESS;
        }

        if (! $ip) {
            $this->error('Indiquez l\'adresse IP : php artisan portfolio:ip-ban 203.0.113.7 --lift');

            return self::FAILURE;
        }

        $active = IpBan::active()->where('ip_address', $ip)->get();
        if ($active->isEmpty()) {
            $this->warn("{$ip} n'est pas bannie.");

            return self::SUCCESS;
        }

        $active->each(fn (IpBan $ban) => $bans->lift($ban));
        $this->info("Bannissement de {$ip} levé.");

        return self::SUCCESS;
    }
}
