<?php

use App\Support\EditorContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La description des projets passe au format Editor.js (texte enrichi).
     * Le texte existant est converti : un paragraphe par bloc, retours à la ligne conservés.
     */
    public function up(): void
    {
        DB::table('projects')->orderBy('id')->each(function ($project) {
            $decoded = json_decode((string) $project->description, true);
            if (is_array($decoded) && isset($decoded['blocks'])) {
                return; // déjà converti
            }

            DB::table('projects')->where('id', $project->id)->update([
                'description' => json_encode(EditorContent::fromText((string) $project->description), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('projects')->orderBy('id')->each(function ($project) {
            $decoded = json_decode((string) $project->description, true);
            if (is_array($decoded) && isset($decoded['blocks'])) {
                DB::table('projects')->where('id', $project->id)->update(['description' => EditorContent::toText($decoded)]);
            }
        });
    }
};
