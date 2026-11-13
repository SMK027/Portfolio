<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // File d'attente (voir App\Services\WaitingRoom) : un ticket par visiteur,
        // l'identifiant auto-incrémenté donne l'ordre d'arrivée.
        Schema::create('waiting_room_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->timestamp('admitted_at')->nullable()->index();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_room_tickets');
    }
};
