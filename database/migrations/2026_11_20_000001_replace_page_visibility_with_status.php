<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Visibilité publique / privée remplacée par un statut (voir App\Models\Page::STATUSES). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('status', 20)->default('public')->after('intro');
            $table->string('password')->nullable()->after('status');
        });
        DB::table('pages')->where('is_public', false)->update(['status' => 'private']);
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('is_public'));
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->boolean('is_public')->default(true)->after('intro'));
        DB::table('pages')->whereNotIn('status', ['public', 'unlisted'])->update(['is_public' => false]);
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn(['status', 'password']));
    }
};
