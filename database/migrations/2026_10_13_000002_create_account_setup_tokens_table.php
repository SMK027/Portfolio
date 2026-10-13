<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons des liens envoyés depuis l'administration (invitation, réinitialisation) :
 * séparés de « mot de passe oublié » pour avoir leur propre durée de validité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_setup_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_setup_tokens');
    }
};
