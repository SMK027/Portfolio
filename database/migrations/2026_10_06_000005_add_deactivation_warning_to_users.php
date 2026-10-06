<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personnel : date d'envoi de l'avertissement de désactivation programmée
 * (remise à zéro quand la date de désactivation change).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivation_warned_at')->nullable()->after('deactivates_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('deactivation_warned_at'));
    }
};
