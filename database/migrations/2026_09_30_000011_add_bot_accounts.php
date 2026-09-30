<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comptes « bot » (connexion au panel par code d'application) et codes
     * activables / désactivables (la suppression est définitive).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('global_role', ['superadmin', 'admin', 'moderator', 'user', 'service', 'bot'])->default('user')->change();
        });

        Schema::table('service_tokens', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('last_used_ip');
        });

        // Les codes révoqués étaient définitivement inutilisables : ils sont supprimés
        // (le journal d'activité conserve leur historique).
        DB::table('service_tokens')->whereNotNull('revoked_at')->delete();

        Schema::table('service_tokens', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_tokens', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable();
        });
        DB::table('service_tokens')->whereNotNull('disabled_at')->update(['revoked_at' => DB::raw('disabled_at')]);
        Schema::table('service_tokens', fn (Blueprint $table) => $table->dropColumn('disabled_at'));

        DB::table('users')->where('global_role', 'bot')->delete();
        Schema::table('users', function (Blueprint $table) {
            $table->enum('global_role', ['superadmin', 'admin', 'moderator', 'user', 'service'])->default('user')->change();
        });
    }
};
