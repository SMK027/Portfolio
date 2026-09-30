<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const COLUMNS = ['github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn', 'website_url' => 'Site web'];

    /**
     * Les champs GitHub / LinkedIn / site web deviennent une liste libre
     * de réseaux sociaux (nom + URL). Les valeurs existantes sont reprises.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('about');
        });

        foreach (DB::table('profiles')->get() as $profile) {
            $links = [];
            foreach (self::COLUMNS as $column => $name) {
                if (filled($profile->{$column})) {
                    $links[] = ['name' => $name, 'url' => $profile->{$column}];
                }
            }
            DB::table('profiles')->where('id', $profile->id)->update(['social_links' => json_encode($links)]);
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('github_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website_url')->nullable();
        });

        foreach (DB::table('profiles')->get() as $profile) {
            $values = [];
            foreach (json_decode((string) $profile->social_links, true) ?: [] as $link) {
                $column = array_search($link['name'] ?? '', self::COLUMNS, true);
                if ($column && ! isset($values[$column])) {
                    $values[$column] = $link['url'];
                }
            }
            if ($values) {
                DB::table('profiles')->where('id', $profile->id)->update($values);
            }
        }

        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('social_links'));
    }
};
