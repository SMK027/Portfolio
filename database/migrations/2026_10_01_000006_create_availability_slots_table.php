<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Disponibilités ponctuelles (un jour précis), en plus des plages hebdomadaires. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
    }
};
