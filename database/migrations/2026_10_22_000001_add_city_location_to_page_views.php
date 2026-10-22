<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques : région, ville et coordonnées du centre de la ville
 * (base locale DB-IP City Lite), conservées après l'effacement de l'IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('region', 100)->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('region');
            $table->decimal('latitude', 5, 2)->nullable()->after('city');
            $table->decimal('longitude', 5, 2)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn(['region', 'city', 'latitude', 'longitude']);
        });
    }
};
