<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'organisme d'une certification devient facultatif (Pix, niveau de langue…).
     */
    public function up(): void
    {
        Schema::table('certifications', function (Blueprint $table) {
            $table->string('issuer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certifications', function (Blueprint $table) {
            $table->string('issuer')->nullable(false)->change();
        });
    }
};
