<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historique des connexions à la machine SSH (relevé dans le journal de l'agent). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_connections', function (Blueprint $table) {
            $table->id();
            $table->timestamp('connected_at', 3)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('channel', 10);
            $table->string('username', 32)->nullable();
            $table->string('result', 80);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_connections');
    }
};
