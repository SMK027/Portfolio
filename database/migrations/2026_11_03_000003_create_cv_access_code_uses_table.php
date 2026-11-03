<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des téléchargements du CV par code d'accès : quand, d'où
     * (pays / ville approximatifs) et avec quel appareil.
     */
    public function up(): void
    {
        Schema::create('cv_access_code_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_access_code_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('device', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->timestamp('used_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_access_code_uses');
    }
};
