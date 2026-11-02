<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Synchronisation avec Google Agenda : périodes occupées importées (aucun créneau n'y est
 * proposé) et identifiant de l'événement créé pour chaque rendez-vous confirmé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_busy_periods', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->index('starts_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_busy_periods');
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('google_event_id'));
    }
};
