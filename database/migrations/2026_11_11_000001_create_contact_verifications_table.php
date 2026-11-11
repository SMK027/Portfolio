<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un enregistrement par code de vérification envoyé : il porte le message en attente
        // (chiffré) et sert au décompte des envois par adresse IP et par adresse e-mail.
        Schema::create('contact_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('code_hash', 64);
            $table->text('payload');
            $table->decimal('recaptcha_score', 3, 2)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('contact_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_verifications');
    }
};
