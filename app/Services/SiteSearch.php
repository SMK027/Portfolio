<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Certification;
use App\Models\Diploma;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Page;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recherche dans le contenu public du portfolio : seules les pages publiques
 * sont fouillées (une page privée n'apparaît jamais, même pour un administrateur).
 *
 * Insensible aux accents et à la casse. Sans résultat exact, une seconde passe
 * tolère les fautes de frappe (distance de Levenshtein) et propose la correction.
 */
class SiteSearch
{
    public const MIN_LENGTH = 2;

    /** Passe tolérante aux fautes de frappe en cours. */
    protected bool $fuzzy = false;

    /** Mots corrigés lors de la passe tolérante : saisi => trouvé. */
    protected array $corrections = [];

    /** Recherche corrigée (« Vouliez-vous dire … ? »), après une passe tolérante fructueuse. */
    public function suggestion(string $query): ?string
    {
        if (! $this->corrections) {
            return null;
        }

        return collect(explode(' ', trim(preg_replace('/\s+/u', ' ', $query))))
            ->map(fn ($word) => $this->corrections[Str::lower(Str::ascii($word))] ?? $word)
            ->implode(' ');
    }

    /** Termes à surligner : mots recherchés et leurs corrections. */
    public function terms(string $query): array
    {
        $words = array_filter(explode(' ', Str::lower(Str::ascii(trim($query)))), fn ($w) => mb_strlen($w) >= self::MIN_LENGTH);

        return array_values(array_unique([...$words, ...array_values($this->corrections)]));
    }

    /** Texte échappé, termes recherchés entourés de <mark> (sans tenir compte des accents). */
    public static function highlight(?string $text, array $terms): \Illuminate\Support\HtmlString
    {
        $html = preg_replace_callback('/[\p{L}\p{N}]+|[^\p{L}\p{N}]+/u', function ($m) use ($terms) {
            $token = $m[0];
            $plain = Str::lower(Str::ascii($token));
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($plain, $term)) {
                    return '<mark class="rounded bg-amber-100 px-0.5 text-inherit">'.e($token).'</mark>';
                }
            }

            return e($token);
        }, (string) $text);

        return new \Illuminate\Support\HtmlString($html ?? e($text));
    }

    /**
     * @return Collection<int, array{type: string, title: string, url: string, excerpt: string}>
     */
    public function search(string $query, ?User $user, int $limit = 30): Collection
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        $this->fuzzy = false;
        $this->corrections = [];
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return collect();
        }

        $results = $this->run($query, $limit);

        // Aucun résultat : seconde passe tolérante aux fautes de frappe (mots d'au moins 4 lettres).
        if ($results->isEmpty() && collect(explode(' ', $query))->contains(fn ($w) => mb_strlen($w) >= 4)) {
            $this->fuzzy = true;
            $results = $this->run($query, $limit);
            $this->fuzzy = false;
        }

        return $results;
    }

    protected function run(string $query, int $limit): Collection
    {
        // Recherche publique : une page privée n'est fouillée pour personne,
        // administrateurs compris (ils consultent les pages privées par le menu).
        $can = fn (string $key) => (bool) Page::findByKey($key)?->is_public;
        $results = collect();

        if ($can('veille')) {
            $results = $results->merge($this->find(Article::published()->orderByDesc('published_at'), ['title', 'excerpt', 'content'], $query)
                ->map(fn (Article $a) => $this->result('Article', $a->title, route('articles.show', $a), $query, $a->excerpt, $a->content)));
        }
        if ($can('projets')) {
            $results = $results
                ->merge($this->find(Project::orderByDesc('published_on'), ['title', 'description', 'description_markdown'], $query)
                    ->map(fn (Project $p) => $this->result('Projet', $p->title, route('projects.show', $p), $query, $p->description)))
                ->merge($this->find(Theme::ordered(), ['name', 'description'], $query)
                    ->map(fn (Theme $t) => $this->result('Thème', $t->name, route('projects.theme', $t), $query, $t->description)));
        }
        if ($can('competences')) {
            $results = $results->merge($this->find(Skill::ordered(), ['name', 'category', 'description'], $query)
                ->map(fn (Skill $s) => $this->result('Compétence', $s->name, route('competences'), $query, $s->category, $s->description)));
        }
        if ($can('experiences')) {
            $results = $results->merge($this->find(Experience::ordered(), ['title', 'company', 'description', 'description_markdown'], $query)
                ->map(fn (Experience $e) => $this->result('Expérience', $e->title.($e->company ? ' — '.$e->company : ''), route('experiences'), $query, $e->description)));
        }
        if ($can('formations')) {
            $results = $results->merge($this->find(Education::ordered(), ['title', 'institution', 'description'], $query)
                ->map(fn (Education $e) => $this->result('Formation', $e->title.($e->institution ? ' — '.$e->institution : ''), route('formations'), $query, $e->description)));
        }
        if ($can('diplomes')) {
            $results = $results->merge($this->find(Diploma::ordered(), ['title', 'institution', 'description'], $query)
                ->map(fn (Diploma $d) => $this->result('Diplôme', $d->title, route('diplomes'), $query, $d->institution, $d->description)));
        }
        if ($can('certifications')) {
            $results = $results->merge($this->find(Certification::ordered(), ['name', 'issuer', 'description'], $query)
                ->map(fn (Certification $c) => $this->result('Certification', $c->name, route('certifications'), $query, $c->issuer, $c->description)));
        }

        // Les titres correspondants d'abord, puis l'ordre de chaque section.
        return $results->filter()->sortBy(fn ($r) => $r['inTitle'] ? 0 : 1)->values()->take($limit)
            ->map(fn ($r) => Arr::except($r, 'inTitle'));
    }

    /** Candidats SQL (tous les mots présents dans l'un des champs). */
    protected function find($builder, array $columns, string $query): Collection
    {
        // Passe tolérante : pas de filtre SQL (contenu de taille modeste), comparaison en PHP.
        if ($this->fuzzy) {
            return $builder->limit(300)->get();
        }

        foreach (explode(' ', $query) as $word) {
            // Les contenus Editor.js sont du JSON où les accents sont échappés (é → \u00e9).
            $variants = array_unique([$word, trim(json_encode($word), '"')]);
            $builder->where(fn ($q) => collect($columns)->each(fn ($c) => collect($variants)->each(
                // ESCAPE explicite : même comportement sous MariaDB et SQLite (antislash littéral).
                fn ($v) => $q->orWhereRaw($q->getGrammar()->wrap($c)." LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v).'%'])
            )));
        }

        return $builder->limit(50)->get();
    }

    /**
     * Résultat avec extrait centré sur le premier mot trouvé ; null si la
     * correspondance ne venait que de la structure JSON (clés Editor.js).
     */
    protected function result(string $type, string $title, string $url, string $query, mixed ...$fields): ?array
    {
        $text = collect($fields)->map(fn ($f) => $this->plainText($f))->filter()->implode(' — ');
        $haystack = Str::lower(Str::ascii($title.' '.$text));
        $words = explode(' ', Str::lower(Str::ascii($query)));
        $corrections = [];
        foreach ($words as $i => $word) {
            if (str_contains($haystack, $word)) {
                continue;
            }
            $match = $this->fuzzy ? $this->closestWord($word, $haystack) : null;
            if ($match === null) {
                return null;
            }
            $corrections[$word] = $words[$i] = $match;
        }
        $this->corrections += $corrections;

        $plain = Str::ascii(Str::lower($text));
        $position = mb_strpos($plain, $words[0]);
        $start = $position === false ? 0 : max(0, $position - 60);
        $excerpt = ($start > 0 ? '…' : '').Str::limit(mb_substr($text, $start), 180);

        return [
            'type'    => $type,
            'title'   => $title,
            'url'     => $url,
            'excerpt' => $excerpt,
            'inTitle' => collect($words)->every(fn ($w) => str_contains(Str::lower(Str::ascii($title)), $w)),
        ];
    }

    /**
     * Mot du texte le plus proche (1 faute jusqu'à 6 lettres, 2 au-delà), ou null.
     * Les débuts de mots sont aussi comparés (« developp » ≈ « développement »).
     */
    protected function closestWord(string $word, string $haystack): ?string
    {
        if (mb_strlen($word) < 4) {
            return null;
        }

        $max = strlen($word) >= 7 ? 2 : 1;
        $best = null;
        $bestDistance = $max + 1;
        foreach (array_unique(preg_split('/[^a-z0-9]+/', $haystack, -1, PREG_SPLIT_NO_EMPTY)) as $token) {
            if (abs(strlen($token) - strlen($word)) > $max && strlen($token) < strlen($word)) {
                continue;
            }
            $distance = min(levenshtein($word, $token), levenshtein($word, substr($token, 0, strlen($word))));
            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$token, $distance];
            }
        }

        return $best;
    }

    /** Texte brut d'un champ simple ou d'un contenu Editor.js. */
    protected function plainText(mixed $value): string
    {
        if (is_string($value) && str_starts_with(ltrim($value), '{')) {
            $value = json_decode($value, true) ?? $value;
        }
        if (is_array($value)) {
            // Seuls les champs de texte des blocs (pas l'alignement, le style, les URL…).
            $value = collect($value['blocks'] ?? [])
                ->flatMap(fn ($b) => collect(Arr::dot((array) ($b['data'] ?? [])))
                    ->filter(fn ($v, $key) => is_string($v) && preg_match('/(^|\.)(text|caption|title|message|content|code|\d+)$/', $key)))
                ->implode(' ');
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5)));
    }
}
