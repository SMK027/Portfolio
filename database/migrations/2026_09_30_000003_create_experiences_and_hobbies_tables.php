<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expériences professionnelles et loisirs, avec leurs pages publiques.
     * Les pages sont créées privées : elles apparaîtront une fois remplies et publiées.
     */
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('date_precision', 5)->default('month');
            $table->string('title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->string('contract_type', 50)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('hobbies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $position = fn (string $key, int $fallback) => DB::table('pages')->where('key', $key)->value('position') ?? $fallback;
        $now = now();

        DB::table('pages')->insertOrIgnore([
            [
                'key' => 'experiences', 'title' => 'Expériences', 'intro' => 'Mon parcours professionnel.',
                'is_public' => false, 'position' => $position('formations', 1), 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'loisirs', 'title' => 'Loisirs', 'intro' => 'Ce qui m\'anime en dehors du travail.',
                'is_public' => false, 'position' => $position('veille', 6), 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('key', ['experiences', 'loisirs'])->delete();
        Schema::dropIfExists('hobbies');
        Schema::dropIfExists('experiences');
    }
};
