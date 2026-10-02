<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * CV : téléchargement activable / désactivable, fichier déplacé hors du
 * dossier public (servi par /cv, qui vérifie le réglage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('cv_downloadable')->default(true);
        });

        DB::table('profiles')->whereNotNull('cv_path')->get(['id', 'cv_path'])->each(function ($profile) {
            if (! Storage::disk('public')->exists($profile->cv_path)) {
                return;
            }
            $target = 'cv/'.basename($profile->cv_path);
            Storage::disk('local')->put($target, Storage::disk('public')->get($profile->cv_path));
            Storage::disk('public')->delete($profile->cv_path);
            DB::table('profiles')->where('id', $profile->id)->update(['cv_path' => $target]);
        });
    }

    public function down(): void
    {
        DB::table('profiles')->whereNotNull('cv_path')->get(['id', 'cv_path'])->each(function ($profile) {
            if (Storage::disk('local')->exists($profile->cv_path)) {
                $target = 'profile/'.basename($profile->cv_path);
                Storage::disk('public')->put($target, Storage::disk('local')->get($profile->cv_path));
                Storage::disk('local')->delete($profile->cv_path);
                DB::table('profiles')->where('id', $profile->id)->update(['cv_path' => $target]);
            }
        });

        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('cv_downloadable'));
    }
};
