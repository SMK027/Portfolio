<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->date('published_on');
            $table->longText('description');
            $table->timestamps();
        });

        Schema::create('project_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100)->nullable();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->boolean('is_image')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('thumbnail_file_id')->nullable()->after('description')
                ->constrained('project_files')->nullOnDelete();
        });

        Schema::create('project_skill', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'skill_id']);
        });

        Schema::create('project_theme', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'theme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_theme');
        Schema::dropIfExists('project_skill');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('thumbnail_file_id');
        });
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('project_links');
        Schema::dropIfExists('projects');
    }
};
