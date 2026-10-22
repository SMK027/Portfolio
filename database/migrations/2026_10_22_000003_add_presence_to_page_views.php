<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques en temps réel : dernier signal de présence de la page (battement
 * envoyé tant qu'elle est visible) et moment où le visiteur l'a quittée ou masquée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('duration');
            $table->timestamp('left_at')->nullable()->after('last_seen_at');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn(['last_seen_at', 'left_at']);
        });
    }
};
