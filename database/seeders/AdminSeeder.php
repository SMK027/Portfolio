<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    /**
     * Crée le compte administrateur par défaut.
     * Changez le mot de passe immédiatement après la première connexion.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@app.local'],
            [
                'username'    => 'admin',
                'name'        => 'Administrateur',
                'password'    => Hash::make('password'),
                'global_role' => 'superadmin',
            ]
        );
    }
}
