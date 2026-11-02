<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Easter eggs : éléments de la page secrète (texte, image, lien, effet visuel),
 * chacun avec ses conditions d'affichage et, éventuellement, sa propre combinaison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easter_eggs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('type', 20);                     // text, image, link, effect
            $table->text('body')->nullable();               // Markdown
            $table->string('image_path')->nullable();       // disque privé
            $table->string('link_url', 2048)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->string('effect', 20)->nullable();       // confetti, matrix, barrel-roll, invert
            // Combinaison propre : l'élément reste verrouillé tant qu'elle n'est pas tapée.
            $table->string('unlock_sequence', 255)->nullable();
            $table->string('hint', 255)->nullable();
            // Conditions d'affichage
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('weekdays')->nullable();           // 1 (lundi) … 7 (dimanche)
            $table->string('time_from', 5)->nullable();     // HH:MM
            $table->string('time_to', 5)->nullable();
            $table->string('device', 10)->nullable();       // mobile, desktop
            $table->json('countries')->nullable();          // codes ISO
            $table->string('country_mode', 10)->default('only'); // only, except
            $table->unsignedSmallInteger('min_unlocked')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easter_eggs');
    }
};
