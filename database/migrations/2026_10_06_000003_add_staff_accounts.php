<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes « Personnel » : humains (mot de passe, double authentification, clé de
 * sécurité) aux autorisations limitées comme les bots, avec désactivation programmable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('global_role', ['superadmin', 'admin', 'moderator', 'user', 'service', 'bot', 'staff'])->default('user')->change();
            // null : jamais désactivé automatiquement
            $table->timestamp('deactivates_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        DB::table('users')->where('global_role', 'staff')->delete();
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivates_at');
            $table->enum('global_role', ['superadmin', 'admin', 'moderator', 'user', 'service', 'bot'])->default('user')->change();
        });
    }
};
