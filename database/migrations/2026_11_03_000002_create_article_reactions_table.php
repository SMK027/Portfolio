<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réactions anonymes aux articles : une par emoji et par visiteur,
     * reconnu par une empreinte (IP + navigateur, hachée avec la clé de l'application).
     */
    public function up(): void
    {
        Schema::create('article_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 20);
            $table->char('visitor', 64);
            $table->timestamp('created_at')->nullable();
            $table->unique(['article_id', 'emoji', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_reactions');
    }
};
