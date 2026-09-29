<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date d'envoi de la notification e-mail d'un message de contact
     * (nulle si l'envoi a échoué : le message reste consultable dans l'admin).
     */
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('recaptcha_score');
        });

        // Les messages existants ne sont pas signalés comme non notifiés.
        DB::table('contact_messages')->update(['notified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
