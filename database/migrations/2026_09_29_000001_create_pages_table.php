<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages publiques du site : chacune peut être rendue privée
     * (visible uniquement par les administrateurs connectés).
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('title', 100);
            $table->string('intro', 500)->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        $pages = [
            ['home',           'Accueil',             null],
            ['formations',     'Formations',          'Mon parcours de formation.'],
            ['diplomes',       'Diplômes',            'Les diplômes que j\'ai obtenus.'],
            ['certifications', 'Certifications',      'Mes certifications professionnelles.'],
            ['competences',    'Compétences',         'Les technologies et savoir-faire que je maîtrise.'],
            ['projets',        'Projets',             'Une sélection de mes réalisations, classées par thème.'],
            ['veille',         'Veille',              'Ma veille technologique : articles, analyses et découvertes.'],
            ['contact',        'Contact',             'Une question, une opportunité ? Écrivez-moi.'],
        ];

        DB::table('pages')->insert(array_map(fn ($page, $i) => [
            'key'        => $page[0],
            'title'      => $page[1],
            'intro'      => $page[2],
            'is_public'  => true,
            'position'   => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ], $pages, array_keys($pages)));
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
