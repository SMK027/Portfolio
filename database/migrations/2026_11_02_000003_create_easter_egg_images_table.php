<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Plusieurs images par easter egg (carrousel) : l'image unique existante y est reprise. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easter_egg_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('easter_egg_id')->constrained()->cascadeOnDelete();
            $table->string('path');                         // disque privé
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        DB::table('easter_eggs')->whereNotNull('image_path')->orderBy('id')->each(fn ($egg) => DB::table('easter_egg_images')->insert([
            'easter_egg_id' => $egg->id, 'path' => $egg->image_path, 'position' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]));

        Schema::table('easter_eggs', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }

    public function down(): void
    {
        Schema::table('easter_eggs', fn (Blueprint $table) => $table->string('image_path')->nullable()->after('body'));
        DB::table('easter_egg_images')->orderBy('position')->get()->unique('easter_egg_id')
            ->each(fn ($image) => DB::table('easter_eggs')->where('id', $image->easter_egg_id)->update(['image_path' => $image->path]));
        Schema::dropIfExists('easter_egg_images');
    }
};
