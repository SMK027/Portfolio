<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Succès : définis en administration (titre, seuil…), chacun lié à un déclencheur
     * codé (App\Achievements\Triggers). Progression des visiteurs anonymes repérés
     * par un jeton aléatoire (cookie fonctionnel), et succès débloqués pour les statistiques.
     */
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('description', 255)->nullable();
            $table->string('icon', 40)->default('trophy');
            $table->string('category', 20);
            $table->string('trigger', 40)->index();
            $table->unsignedInteger('threshold')->default(1);
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('achievement_visitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->json('progress')->nullable();
            $table->json('seen')->nullable();
            $table->timestamp('last_beat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('achievement_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_visitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->unique(['achievement_id', 'achievement_visitor_id']);
        });

        $now = now();
        DB::table('achievements')->insert(array_map(fn (array $row, int $i) => $row + [
            'position' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $rows = \App\Achievements\Triggers::defaults(), array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_unlocks');
        Schema::dropIfExists('achievement_visitors');
        Schema::dropIfExists('achievements');
    }
};
