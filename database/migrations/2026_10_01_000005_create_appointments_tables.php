<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prise de rendez-vous : disponibilités hebdomadaires, jours fermés et
 * demandes des visiteurs (à confirmer depuis l'administration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday'); // 1 = lundi … 7 = dimanche
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('availability_closures', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('name', 150);
            $table->string('email', 255);
            $table->string('phone', 40)->nullable();
            $table->string('topic', 100);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // pending, confirmed, declined, cancelled
            $table->text('admin_note')->nullable();           // message joint à la décision
            $table->string('cancel_token', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
            $table->index(['starts_at', 'status']);
        });

        // Page publique, privée tant que les disponibilités ne sont pas configurées.
        DB::table('pages')->insert([
            'key' => 'rendez-vous', 'title' => 'Rendez-vous',
            'intro' => 'Réservez un créneau pour échanger sur un stage, une alternance ou un projet.',
            'is_public' => false, 'position' => (int) DB::table('pages')->max('position') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('settings')->insertOrIgnore([
            ['key' => 'appointments.settings', 'value' => json_encode([
                'duration' => 30, 'notice_hours' => 24, 'horizon_days' => 30,
                'location' => 'Visioconférence — le lien vous sera envoyé à la confirmation.',
                'topics'   => ['Stage', 'Alternance', 'Emploi', 'Projet', 'Autre'],
            ]), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('availability_closures');
        Schema::dropIfExists('availability_rules');
        DB::table('pages')->where('key', 'rendez-vous')->delete();
        DB::table('settings')->where('key', 'appointments.settings')->delete();
    }
};
