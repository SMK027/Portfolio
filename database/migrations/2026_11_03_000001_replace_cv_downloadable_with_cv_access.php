<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CV : politique de téléchargement à trois niveaux (public, restreint par
 * code, privé) à la place du simple interrupteur. Les codes ne sont pas touchés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('cv_access', 16)->default('restricted');
        });

        DB::table('profiles')->update([
            'cv_access' => DB::raw("CASE WHEN cv_downloadable THEN 'restricted' ELSE 'private' END"),
        ]);

        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('cv_downloadable'));
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('cv_downloadable')->default(true);
        });

        DB::table('profiles')->update([
            'cv_downloadable' => DB::raw("CASE WHEN cv_access = 'private' THEN 0 ELSE 1 END"),
        ]);

        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('cv_access'));
    }
};
