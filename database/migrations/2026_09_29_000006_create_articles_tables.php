<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Articles de veille technologique.
     * published_at nul = brouillon ; date future = publication programmée.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->dateTime('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('article_coauthor', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'user_id']);
        });

        Schema::create('article_theme', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'theme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_theme');
        Schema::dropIfExists('article_coauthor');
        Schema::dropIfExists('articles');
    }
};
