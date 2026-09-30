<?php

use App\Support\EditorContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * « Missions et réalisations » des expériences : texte enrichi (éditeur
     * visuel ou Markdown). Le texte existant est converti en paragraphes.
     */
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
            $table->string('description_editor', 10)->default('blocks')->after('description');
            $table->longText('description_markdown')->nullable()->after('description_editor');
        });

        DB::table('experiences')->whereNotNull('description')->orderBy('id')->each(function ($experience) {
            $decoded = json_decode((string) $experience->description, true);
            if (is_array($decoded) && isset($decoded['blocks'])) {
                return; // déjà converti
            }

            DB::table('experiences')->where('id', $experience->id)->update([
                'description' => trim((string) $experience->description) === ''
                    ? null
                    : json_encode(EditorContent::fromText((string) $experience->description), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('experiences')->whereNotNull('description')->orderBy('id')->each(function ($experience) {
            $decoded = json_decode((string) $experience->description, true);
            if (is_array($decoded) && isset($decoded['blocks'])) {
                DB::table('experiences')->where('id', $experience->id)->update(['description' => EditorContent::toText($decoded)]);
            }
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn(['description_editor', 'description_markdown']);
            $table->text('description')->nullable()->change();
        });
    }
};
