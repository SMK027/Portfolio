<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Historique des versions des articles (titre, résumé, contenu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 255)->nullable(); // conservé si le compte est supprimé
            $table->string('title', 255);
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['article_id', 'id']);
        });

        // Version de départ pour les articles existants.
        DB::table('articles')->orderBy('id')->each(function ($article) {
            DB::table('article_revisions')->insert([
                'article_id' => $article->id,
                'user_id'    => $article->author_id,
                'user_name'  => DB::table('users')->where('id', $article->author_id)->value('name'),
                'title'      => $article->title,
                'excerpt'    => $article->excerpt,
                'content'    => $article->content,
                'note'       => 'Version initiale',
                'created_at' => $article->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
