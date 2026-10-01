<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques de visite sans cookie : une ligne par page vue, sans IP ni
 * identifiant durable (empreinte de visiteur renouvelée chaque jour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255);
            $table->string('route', 60)->nullable();
            $table->string('referrer_host', 100)->nullable();
            $table->char('visitor', 16);   // empreinte du jour (non réversible)
            $table->date('viewed_on');
            $table->timestamp('created_at')->nullable();

            $table->index(['viewed_on', 'path']);
            $table->index(['viewed_on', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
