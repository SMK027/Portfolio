<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes de gestion de la machine SSH : accès root par clé, chacun lié à un
 * super-administrateur du portfolio (appliqué tant qu'il l'est et que son compte est actif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 32)->unique();
            $table->json('public_keys');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_managers');
    }
};
