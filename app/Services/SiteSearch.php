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
 * Recherche dans le contenu public du portfolio, en respectant la visibilité
 * des pages (une page privée n'est jamais fouillée pour un visiteur).
 */
class SiteSearch
{
    public const MIN_LENGTH = 2;

    /**
     * @return Collection<int, array{type: string, title: string, url: string, excerpt: string}>
     */
    public function search(string $query, ?User $user, int $limit = 30): Collection
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return collect();
        }

        $can = fn (string $key) => Page::isKeyAccessibleBy($key, $user);
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
        foreach ($words as $word) {
            if (! str_contains($haystack, $word)) {
                return null;
            }
        }

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
