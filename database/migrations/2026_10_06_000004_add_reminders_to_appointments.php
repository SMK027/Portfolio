<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rappels envoyés aux visiteurs : la veille (24 h avant) et 1 h avant le rendez-vous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('day_reminder_sent_at')->nullable()->after('consented_at');
            $table->timestamp('hour_reminder_sent_at')->nullable()->after('day_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['day_reminder_sent_at', 'hour_reminder_sent_at']);
        });
    }
};
