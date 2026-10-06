<?php

namespace App\Console\Commands;

use App\Services\LoginPath;
use Illuminate\Console\Command;

/**
 * Affiche ou modifie l'adresse de la page de connexion (voir App\Services\LoginPath).
 * Recours en cas d'adresse oubliée : --reset rétablit /login.
 */
class LoginPathCommand extends Command
{
    protected $signature = 'portfolio:login-path
        {path? : Nouvelle adresse (ex. acces-prive)}
        {--reset : Rétablit l\'adresse par défaut /login}';

    protected $description = 'Affiche ou modifie l\'adresse de la page de connexion au panel';

    public function handle(): int
    {
        $path = $this->option('reset') ? LoginPath::DEFAULT : LoginPath::normalize($this->argument('path'));

        if ($path === '') {
            $this->line('Adresse de connexion : '.url(LoginPath::configured()));

            return self::SUCCESS;
        }

        if ($problem = LoginPath::problem($path)) {
            $this->error($problem);

            return self::FAILURE;
        }

        LoginPath::set($path);
        $this->info('Adresse de connexion : '.url($path));

        return self::SUCCESS;
    }
}
