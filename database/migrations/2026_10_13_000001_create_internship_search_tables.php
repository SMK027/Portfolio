<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi de la recherche de stage : entreprises contactées et remarques
 * sur l'état général de la recherche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('company', 150);
            $table->string('address', 255);
            $table->string('contact_name', 150)->nullable();
            $table->date('contacted_on');
            $table->string('contact_method', 20);           // email, call, letter, in_person
            $table->string('status', 20);                   // pending, refused, accepted, processing
            $table->date('follow_up_on')->nullable();       // seulement en attente / en cours
            $table->string('follow_up_method', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('contacted_on');
        });

        Schema::create('internship_remarks', function (Blueprint $table) {
            $table->id();
            $table->date('noted_on');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_remarks');
        Schema::dropIfExists('internship_contacts');
    }
};
