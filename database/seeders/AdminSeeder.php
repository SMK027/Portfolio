<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Crée le compte administrateur par défaut, uniquement si aucun
     * administrateur n'existe (exécuté à chaque démarrage du conteneur).
     * Changez le mot de passe immédiatement après la première connexion.
     */
    public function run(): void
    {
        if (User::whereIn('global_role', ['admin', 'superadmin'])->exists()) {
            return;
        }

        User::create([
            'email'             => 'admin@app.local',
            'username'          => 'admin',
            'name'              => 'Administrateur',
            'password'          => Hash::make('password'),
            'global_role'       => 'superadmin',
            'email_verified_at' => now(),
        ]);

        $this->command?->warn('Administrateur créé : admin@app.local / password — changez ce mot de passe !');
    }
}
