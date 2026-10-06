<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresses IP bannies après des échecs de connexion répétés (voir App\Services\LoginBan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('reason', 255)->nullable();
            // null : bannissement sans date de fin (jusqu'à levée manuelle)
            $table->timestamp('banned_until')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_bans');
    }
};
