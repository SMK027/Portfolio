<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = ['educations', 'diplomas', 'certifications'];

    /**
     * Précision des dates : année, mois et année, ou date complète.
     * Les enregistrements existants passent à l'année : ils avaient été saisis
     * avec des jours approximatifs faute de pouvoir indiquer l'année seule.
     */
    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('date_precision', 5)->default('day')->after('id');
            });
            DB::table($name)->update(['date_precision' => 'year']);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('date_precision'));
        }
    }
};
