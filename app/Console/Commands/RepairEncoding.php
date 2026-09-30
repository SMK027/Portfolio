<?php

namespace App\Console\Commands;

use App\Support\Mojibake;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Détecte et répare les textes mal encodés (« Ã© » au lieu de « é ») dans
 * tout le contenu du portfolio. Simulation par défaut ; --apply pour enregistrer.
 */
class RepairEncoding extends Command
{
    protected $signature = 'portfolio:repair-encoding {--apply : Enregistre les corrections (sinon simple simulation)}';

    protected $description = 'Répare les accents mal encodés (Ã©, â€™…) dans le contenu du site';

    /** Table => colonnes de texte (les colonnes JSON sont décodées puis réparées). */
    protected const COLUMNS = [
        'profiles'       => ['first_name', 'last_name', 'headline', 'location', 'about', 'social_links'],
        'pages'          => ['title', 'intro'],
        'themes'         => ['name', 'description'],
        'skills'         => ['name', 'category', 'description'],
        'educations'     => ['title', 'institution', 'location', 'description'],
        'experiences'    => ['title', 'company', 'location', 'contract_type', 'description', 'description_markdown'],
        'diplomas'       => ['title', 'institution', 'level', 'mention', 'description'],
        'certifications' => ['name', 'issuer', 'credential_id', 'description'],
        'hobbies'        => ['name', 'description'],
        'projects'       => ['title', 'description', 'description_markdown'],
        'project_links'  => ['label'],
        'project_files'  => ['original_name'],
        'articles'       => ['title', 'excerpt', 'content', 'content_markdown', 'review_note'],
        'article_files'  => ['original_name'],
        'announcements'  => ['title', 'message', 'link_label'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $total = 0;

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));

            foreach (DB::table($table)->select(['id', ...$columns])->orderBy('id')->get() as $record) {
                $changes = [];
                foreach ($columns as $column) {
                    $value = $record->{$column};
                    if (! is_string($value)) {
                        continue;
                    }

                    $fixed = $this->repair($value);
                    if ($fixed !== null && $fixed !== $value) {
                        $changes[$column] = $fixed;
                        $rows[] = [$table, $column, $record->id, $this->sample($value), $this->sample($fixed)];
                    }
                }

                if ($changes) {
                    $total += count($changes);
                    if ($apply) {
                        DB::table($table)->where('id', $record->id)->update($changes);
                    }
                }
            }
        }

        if (! $rows) {
            $this->info('Aucun texte mal encodé trouvé.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Colonne', 'ID', 'Avant', 'Après'], $rows);

        if ($apply) {
            $this->info("{$total} champ(s) réparé(s).");
        } else {
            $this->warn("Simulation : {$total} champ(s) à réparer. Relancez avec --apply pour enregistrer "
                .'(faites d\'abord une sauvegarde : Import / export → Exporter).');
        }

        return self::SUCCESS;
    }

    /**
     * Texte simple ou JSON (contenu Editor.js, liens…) réparé en conservant sa structure.
     * Retourne null si rien n'est à réparer.
     */
    protected function repair(string $value): ?string
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            // Le JSON peut contenir des séquences échappées (Ã©) : on répare le contenu décodé.
            $fixed = Mojibake::fixDeep($decoded);

            return $fixed === $decoded ? null : json_encode($fixed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return Mojibake::suspect($value) ? Mojibake::fix($value) : null;
    }

    /** Extrait lisible autour du premier problème. */
    protected function sample(string $value): string
    {
        $decoded = json_decode($value, true);
        $text = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
        $text = preg_replace('/\s+/u', ' ', strip_tags($text));

        if (preg_match('/[\x{00C2}-\x{00F4}][\x{0080}-\x{00BF}\x{2013}-\x{2022}\x{2026}\x{20AC}\x{2122}]/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            $start = max(0, mb_strlen(substr($text, 0, $m[0][1])) - 20);

            return Str::limit(mb_substr($text, $start), 50);
        }

        return Str::limit($text, 50);
    }
}
