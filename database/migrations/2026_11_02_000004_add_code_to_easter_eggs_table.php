<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Easter eggs de type « Code » : HTML, CSS et JavaScript exécutés dans une iframe isolée
 * (origine opaque, voir EasterEggController::code). Modifiables par les super-administrateurs seulement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('easter_eggs', function (Blueprint $table) {
            $table->mediumText('code_html')->nullable()->after('effect');
            $table->mediumText('code_css')->nullable()->after('code_html');
            $table->mediumText('code_js')->nullable()->after('code_css');
            $table->boolean('code_allow_cdn')->default(false)->after('code_js');
        });
    }

    public function down(): void
    {
        Schema::table('easter_eggs', fn (Blueprint $table) => $table->dropColumn(['code_html', 'code_css', 'code_js', 'code_allow_cdn']));
    }
};
