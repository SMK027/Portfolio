<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plages hebdomadaires limitées à une période (ex. : chaque mardi du 01/11 au 14/11).
 * Sans dates, la plage s'applique toutes les semaines, sans fin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_rules', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('end_time');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('availability_rules', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on']);
        });
    }
};
