<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « articles.author » est retirée : articles.write suffit pour changer l'auteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('global_role', ['service', 'bot'])->whereNotNull('permissions')->get(['id', 'permissions'])
            ->each(function ($row) {
                $permissions = json_decode($row->permissions, true) ?: [];
                if (in_array('articles.author', $permissions, true)) {
                    DB::table('users')->where('id', $row->id)->update([
                        'permissions' => json_encode(array_values(array_diff($permissions, ['articles.author']))),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Rien à restaurer : l'autorisation n'existe plus.
    }
};
