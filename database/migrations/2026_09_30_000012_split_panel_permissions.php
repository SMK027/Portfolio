<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Autorisations détaillées par section : la suppression des annonces devient
 * « announcements.delete ». Les comptes qui pouvaient déjà supprimer la gardent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->each(function (array $permissions) {
            if (in_array('announcements.write', $permissions, true) && ! in_array('announcements.delete', $permissions, true)) {
                $permissions[] = 'announcements.delete';
            }

            return $permissions;
        });
    }

    public function down(): void
    {
        $this->each(fn (array $permissions) => array_values(array_diff($permissions, ['announcements.delete'])));
    }

    protected function each(callable $callback): void
    {
        DB::table('users')->whereIn('global_role', ['service', 'bot'])->whereNotNull('permissions')->get(['id', 'permissions'])
            ->each(function ($row) use ($callback) {
                $before = json_decode($row->permissions, true) ?: [];
                $after = $callback($before);
                if ($after !== $before) {
                    DB::table('users')->where('id', $row->id)->update(['permissions' => json_encode(array_values($after))]);
                }
            });
    }
};
