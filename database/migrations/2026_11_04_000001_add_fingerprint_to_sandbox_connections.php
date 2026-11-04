<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de la machine SSH : empreinte unique de chaque entrée du journal de l'agent.
 * Le filtre précédent (date de la dernière ligne) perdait les millièmes de seconde : une
 * même connexion était réenregistrée, et notifiée, à chaque relevé. Les doublons déjà
 * enregistrés sont supprimés (première occurrence conservée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sandbox_connections', function (Blueprint $table) {
            $table->char('fingerprint', 40)->nullable()->unique()->after('id');
        });

        $seen = [];
        DB::table('sandbox_connections')->orderBy('id')->chunkById(1000, function ($rows) use (&$seen) {
            $duplicates = [];
            foreach ($rows as $row) {
                $key = implode('|', [substr((string) $row->connected_at, 0, 19), $row->ip_address, $row->channel, $row->username, $row->result]);
                if (isset($seen[$key])) {
                    $duplicates[] = $row->id;
                } else {
                    $seen[$key] = true;
                }
            }
            if ($duplicates) {
                DB::table('sandbox_connections')->whereIn('id', $duplicates)->delete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sandbox_connections', function (Blueprint $table) {
            $table->dropUnique(['fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
