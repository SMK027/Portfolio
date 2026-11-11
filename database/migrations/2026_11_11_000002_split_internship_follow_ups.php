<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recherche de stage : la relance devient la date de la dernière relance effectuée
 * (followed_up_on, conservée quel que soit le résultat), distincte de la relance prévue
 * (follow_up_on, rappel facultatif tant qu'une réponse est attendue). Le moyen de relance
 * n'est plus saisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_contacts', function (Blueprint $table) {
            $table->date('followed_up_on')->nullable()->after('status');
        });

        Schema::table('internship_contacts', function (Blueprint $table) {
            $table->dropColumn('follow_up_method');
        });
    }

    public function down(): void
    {
        Schema::table('internship_contacts', function (Blueprint $table) {
            $table->string('follow_up_method', 20)->nullable()->after('follow_up_on');
        });

        Schema::table('internship_contacts', function (Blueprint $table) {
            $table->dropColumn('followed_up_on');
        });
    }
};
