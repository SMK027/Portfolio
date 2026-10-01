<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lien de relecture d'un brouillon, secret et temporaire. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('preview_token', 64)->nullable()->unique();
            $table->timestamp('preview_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['preview_token']);
            $table->dropColumn(['preview_token', 'preview_expires_at']);
        });
    }
};
