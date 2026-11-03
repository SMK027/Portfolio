<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recommandations : un lien d'invitation personnel est remis à l'auteur
 * (ancien tuteur, enseignant…), qui rédige sa recommandation ; elle n'est
 * publiée qu'après validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150); // destinataire de l'invitation (usage interne)
            $table->string('token', 48)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('author_name', 120)->nullable();
            $table->string('author_role', 150)->nullable();
            $table->string('author_company', 150)->nullable();
            $table->string('relationship', 150)->nullable();
            $table->string('author_url', 2048)->nullable();
            $table->text('content')->nullable();
            $table->string('status', 20)->default('invited');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'position']);
        });

        DB::table('pages')->insertOrIgnore([
            'key' => 'recommandations', 'title' => 'Recommandations',
            'intro' => 'Ce que disent de moi les personnes avec qui j\'ai travaillé.',
            'is_public' => false, 'position' => (int) DB::table('pages')->max('position') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        DB::table('pages')->where('key', 'recommandations')->delete();
    }
};
