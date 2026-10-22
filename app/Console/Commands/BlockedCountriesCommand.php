<?php

namespace App\Console\Commands;

use App\Services\CountryBlock;
use Illuminate\Console\Command;

/**
 * Affiche ou modifie la liste noire de pays (voir App\Services\CountryBlock).
 * Recours en cas de blocage de son propre accès : --reset vide la liste.
 */
class BlockedCountriesCommand extends Command
{
    protected $signature = 'portfolio:blocked-countries
        {countries?* : Codes ISO des pays à bloquer (ex. CN RU), remplacent la liste}
        {--reset : Vide la liste noire}';

    protected $description = 'Affiche ou modifie la liste noire de pays';

    public function handle(): int
    {
        if ($this->option('reset')) {
            CountryBlock::set([]);
        } elseif ($countries = $this->argument('countries')) {
            CountryBlock::set($countries);
        }

        $current = CountryBlock::countries();
        $this->line('Pays bloqués : '.($current ? implode(', ', $current) : 'aucun'));

        return self::SUCCESS;
    }
}
