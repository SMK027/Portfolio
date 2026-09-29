<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annonces affichées en bandeau sur toutes les pages publiques
     * (recherche de stage, d'alternance, disponibilité, actualité…).
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('message', 500)->nullable();
            $table->string('style', 20)->default('primary');
            $table->string('link_url', 2048)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dismissible')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
