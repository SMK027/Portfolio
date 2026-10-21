<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ip_bans', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('attempts');
            // Code HTTP renvoyé à l'adresse bannie (null : 403)
            $table->unsignedSmallInteger('http_status')->nullable()->after('reason');
        });

        // Les bannissements automatiques comptent toujours au moins un échec.
        DB::table('ip_bans')->where('attempts', 0)->update(['is_manual' => true]);
    }

    public function down(): void
    {
        Schema::table('ip_bans', function (Blueprint $table) {
            $table->dropColumn(['is_manual', 'http_status']);
        });
    }
};
