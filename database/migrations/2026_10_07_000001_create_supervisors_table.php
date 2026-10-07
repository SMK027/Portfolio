<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superviseurs : valident ponctuellement (bypass à usage unique) une opération
 * pour laquelle le compte connecté n'est pas habilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisors', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('pin_hash');                       // bcrypt (salé)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // administrateur rattaché
            $table->json('permissions');                      // opérations qu'il peut débloquer
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisors');
    }
};
