<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Éditeur choisi (visuel « blocks » ou « markdown ») et source Markdown
     * saisie, pour le contenu des articles et la description des projets.
     * Le contenu affiché reste toujours celui au format Editor.js.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('content_editor', 10)->default('blocks')->after('content');
            $table->longText('content_markdown')->nullable()->after('content_editor');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('description_editor', 10)->default('blocks')->after('description');
            $table->longText('description_markdown')->nullable()->after('description_editor');
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn(['content_editor', 'content_markdown']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['description_editor', 'description_markdown']));
    }
};
