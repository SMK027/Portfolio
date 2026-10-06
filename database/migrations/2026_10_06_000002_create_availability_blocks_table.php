<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horaires bloqués : rendez-vous pris par un autre moyen (e-mail, téléphone…)
 * ou modification de dernière minute. Aucun créneau n'y est proposé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('type', 20);                // appointment, change
            $table->string('channel', 20)->nullable(); // email, phone… (rendez-vous uniquement)
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
    }
};
