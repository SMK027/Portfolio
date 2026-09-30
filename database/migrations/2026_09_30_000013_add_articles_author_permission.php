<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Changer l'auteur d'un article devient « articles.author ». Les comptes qui le
 * pouvaient déjà le gardent : clients API avec articles.write, bots avec articles.publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('global_role', ['service', 'bot'])->whereNotNull('permissions')->get(['id', 'global_role', 'permissions'])
            ->each(function ($row) {
                $permissions = json_decode($row->permissions, true) ?: [];
                $had = $row->global_role === 'service' ? 'articles.write' : 'articles.publish';
                if (in_array($had, $permissions, true) && ! in_array('articles.author', $permissions, true)) {
                    $permissions[] = 'articles.author';
                    DB::table('users')->where('id', $row->id)->update(['permissions' => json_encode($permissions)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('users')->whereIn('global_role', ['service', 'bot'])->whereNotNull('permissions')->get(['id', 'permissions'])
            ->each(fn ($row) => DB::table('users')->where('id', $row->id)->update([
                'permissions' => json_encode(array_values(array_diff(json_decode($row->permissions, true) ?: [], ['articles.author']))),
            ]));
    }
};
