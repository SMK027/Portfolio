<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentification à deux facteurs (comptes humains) : code d'application
 * (TOTP), clés de sécurité (WebAuthn) et codes de secours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();            // chiffré
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // anti-rejeu
            $table->text('two_factor_recovery_codes')->nullable();    // empreintes
        });

        Schema::create('security_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('credential_id', 255)->unique(); // base64url
            $table->text('public_key');                     // PEM
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_keys');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step', 'two_factor_recovery_codes',
        ]));
    }
};
