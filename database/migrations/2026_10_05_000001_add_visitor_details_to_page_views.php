<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques détaillées : IP (effacée après 3 mois), pays, appareil,
 * visiteur récurrent (cookie propre au site, 13 mois), visite et durée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('device', 10)->nullable();
            $table->string('browser', 30)->nullable();
            $table->string('os', 30)->nullable();
            $table->uuid('visitor_id')->nullable();   // cookie « visiteur » (13 mois)
            $table->uuid('session_id')->nullable();   // visite (30 min d'inactivité)
            $table->boolean('is_new_visitor')->default(false);
            $table->unsignedInteger('duration')->nullable(); // secondes de lecture
            $table->index('session_id');
            $table->index('visitor_id');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['visitor_id']);
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'ip_address', 'country', 'device', 'browser', 'os', 'visitor_id', 'session_id', 'is_new_visitor', 'duration']);
        });
    }
};
