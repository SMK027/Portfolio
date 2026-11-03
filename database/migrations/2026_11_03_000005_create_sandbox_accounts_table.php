<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Machine SSH bac à sable : comptes (le panel est la source de vérité, l'agent
 * les applique sur la machine) et page publique d'accès.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 32)->unique();
            $table->string('label', 150)->nullable();
            $table->string('password_hash', 255);
            // Mot de passe chiffré du compte invité seulement (affiché aux visiteurs)
            $table->text('password_encrypted')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        DB::table('pages')->insertOrIgnore([
            'key' => 'machine-ssh', 'title' => 'Machine SSH',
            'intro' => 'Une petite machine Linux en libre accès : connectez-vous en SSH ou directement depuis votre navigateur.',
            'is_public' => false, 'position' => (int) DB::table('pages')->max('position') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_accounts');
        DB::table('pages')->where('key', 'machine-ssh')->delete();
    }
};
