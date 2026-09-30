<?php

namespace App\Services\Transfer;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Certification;
use App\Models\Diploma;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Hobby;
use App\Models\Page;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use App\Services\Markdown\MarkdownToEditorJs;
use App\Services\RemoteImageFetcher;
use App\Support\EditorContent;
use App\Support\PreciseDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Export et import JSON du contenu du portfolio.
 *
 * Format : { "format": "portfolio", "version": 1, "sections": { "projects": [...], ... } }
 * L'import met à jour un élément existant (même clé naturelle : slug, titre…)
 * au lieu de le dupliquer ; il peut donc être relancé sans risque.
 * Les fichiers (photos, images de fond, pièces jointes) ne sont pas inclus.
 */
class ContentTransfer
{
    public const FORMAT = 'portfolio';

    public const VERSION = 1;

    /** Sections dans l'ordre d'import (les thèmes et compétences avant les projets…). */
    public const SECTIONS = [
        'profile'        => 'Présentation',
        'pages'          => 'Pages (titres, visibilité)',
        'themes'         => 'Thèmes',
        'skills'         => 'Compétences',
        'educations'     => 'Formations',
        'experiences'    => 'Expériences',
        'diplomas'       => 'Diplômes',
        'certifications' => 'Certifications',
        'hobbies'        => 'Loisirs',
        'projects'       => 'Projets',
        'articles'       => 'Articles de veille',
        'announcements'  => 'Annonces',
    ];

    /** Rapport de l'import : section => [created, updated, skipped, errors[]]. */
    protected array $report = [];

    protected bool $downloadImages = false;

    protected ?User $importer = null;

    public function __construct(
        protected HtmlToEditorJs $htmlConverter,
        protected RemoteImageFetcher $imageFetcher,
        protected MarkdownToEditorJs $markdownConverter,
    ) {
    }

    /* ======================================================================
     |  EXPORT
     * ==================================================================== */

    /**
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    public function export(array $sections): array
    {
        $data = [];
        foreach (array_keys(self::SECTIONS) as $section) {
            if (in_array($section, $sections, true)) {
                $data[$section] = $this->{'export'.Str::studly($section)}();
            }
        }

        return [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'site'        => config('app.url'),
            'sections'    => $data,
        ];
    }

    protected function exportProfile(): array
    {
        $p = Profile::current();

        return [
            'first_name'   => $p->first_name,
            'last_name'    => $p->last_name,
            'headline'     => $p->headline,
            'location'     => $p->location,
            'email'        => $p->email,
            'phone'        => $p->phone,
            'about'        => $p->about,
            'social_links' => $p->social_links ?? [],
        ];
    }

    protected function exportPages(): array
    {
        return Page::ordered()->get()->map(fn (Page $p) => $p->only(['key', 'title', 'intro', 'is_public', 'position']))->all();
    }

    protected function exportThemes(): array
    {
        return Theme::ordered()->get()->map(fn (Theme $t) => $t->only(['name', 'description', 'position']))->all();
    }

    protected function exportSkills(): array
    {
        return Skill::ordered()->get()->map(fn (Skill $s) => $s->only(['name', 'category', 'level', 'description', 'position']))->all();
    }

    protected function exportEducations(): array
    {
        return Education::ordered()->get()->map(fn (Education $e) => [
            ...$e->only(['title', 'institution', 'location', 'description', 'position']),
            ...$this->exportDates($e, ['start_date', 'end_date']),
        ])->all();
    }

    protected function exportExperiences(): array
    {
        return Experience::ordered()->get()->map(fn (Experience $e) => [
            ...$e->only(['title', 'company', 'location', 'contract_type', 'description', 'position']),
            ...($e->description_editor === 'markdown' ? ['description_markdown' => $e->description_markdown] : []),
            ...$this->exportDates($e, ['start_date', 'end_date']),
        ])->all();
    }

    protected function exportDiplomas(): array
    {
        return Diploma::ordered()->get()->map(fn (Diploma $d) => [
            ...$d->only(['title', 'institution', 'level', 'mention', 'description', 'position']),
            ...$this->exportDates($d, ['obtained_at']),
        ])->all();
    }

    protected function exportCertifications(): array
    {
        return Certification::ordered()->get()->map(fn (Certification $c) => [
            ...$c->only(['name', 'issuer', 'credential_id', 'credential_url', 'description', 'position']),
            ...$this->exportDates($c, ['issued_at', 'expires_at']),
        ])->all();
    }

    protected function exportHobbies(): array
    {
        return Hobby::ordered()->get()->map(fn (Hobby $h) => $h->only(['name', 'description', 'position']))->all();
    }

    protected function exportProjects(): array
    {
        return Project::with('themes', 'skills', 'links')->latestFirst()->get()->map(fn (Project $p) => [
            'title'        => $p->title,
            'slug'         => $p->slug,
            'published_on' => $p->published_on->toDateString(),
            'description'  => $p->description,
            ...($p->description_editor === 'markdown' ? ['description_markdown' => $p->description_markdown] : []),
            'themes'       => $p->themes->pluck('name')->all(),
            'skills'       => $p->skills->pluck('name')->all(),
            'links'        => $p->links->map(fn ($l) => ['label' => $l->label, 'url' => $l->url])->all(),
        ])->all();
    }

    protected function exportArticles(): array
    {
        return Article::with('author', 'coauthors', 'themes')->orderBy('id')->get()->map(fn (Article $a) => [
            'title'        => $a->title,
            'slug'         => $a->slug,
            'excerpt'      => $a->excerpt,
            'content'      => $a->content,
            ...($a->content_editor === 'markdown' ? ['content_markdown' => $a->content_markdown] : []),
            'author'       => $a->author?->email,
            'coauthors'    => $a->coauthors->pluck('email')->all(),
            'themes'       => $a->themes->pluck('name')->all(),
            'is_pinned'    => $a->is_pinned,
            'published_at' => $a->published_at?->toIso8601String(),
        ])->all();
    }

    protected function exportAnnouncements(): array
    {
        return Announcement::ordered()->get()->map(fn (Announcement $a) => [
            ...$a->only(['title', 'message', 'style', 'link_url', 'link_label', 'is_active', 'is_dismissible', 'position']),
            'starts_at' => $a->starts_at?->toIso8601String(),
            'ends_at'   => $a->ends_at?->toIso8601String(),
        ])->all();
    }

    /** Dates exportées dans le format de leur précision (« 2024 », « 2024-09 »…). */
    protected function exportDates(Model $model, array $fields): array
    {
        $precision = $model->datePrecision();
        $dates = ['date_precision' => $precision];
        foreach ($fields as $field) {
            $dates[$field] = PreciseDate::inputValue($model->{$field}, $precision);
        }

        return $dates;
    }

    /**
     * Fichier modèle : un exemple commenté par section.
     *
     * @return array<string, mixed>
     */
    public function example(): array
    {
        return [
            'format'   => self::FORMAT,
            'version'  => self::VERSION,
            '_aide'    => 'Toutes les sections et tous les champs facultatifs peuvent être omis. Dates : « 2024 », « 2024-09 » ou « 2024-09-15 » (la précision est déduite). '
                .'Thèmes et compétences sont désignés par leur nom (créés s\'ils n\'existent pas), auteurs par leur e-mail. '
                .'Un élément déjà présent (même slug, même titre…) est mis à jour. Le contenu d\'un article peut être fourni en HTML (content_html) ou en Markdown (content_markdown), la description d\'un projet en Markdown (description_markdown) : ils sont convertis.',
            'sections' => [
                'profile'        => [
                    'first_name' => 'Léo', 'last_name' => 'Franz', 'headline' => 'Développeur web',
                    'about_html' => '<p>Présentation en <b>HTML</b>…</p>',
                    'social_links' => [['name' => 'X', 'url' => 'https://x.com/smk_027']],
                ],
                'themes'         => [['name' => 'Réseau', 'description' => 'Projets réseau']],
                'skills'         => [['name' => 'Laravel', 'category' => 'Back-end', 'level' => 4]],
                'educations'     => [['title' => 'BTS SIO', 'institution' => 'Lycée', 'start_date' => '2024', 'end_date' => null]],
                'experiences'    => [['title' => 'Développeur web', 'company' => 'Entreprise', 'contract_type' => 'Alternance', 'start_date' => '2025-09', 'end_date' => null, 'description' => 'Missions…']],
                'diplomas'       => [['title' => 'Baccalauréat', 'institution' => 'Académie', 'obtained_at' => '2024', 'mention' => 'Bien']],
                'certifications' => [['name' => 'Pix', 'issuer' => null, 'issued_at' => '2024-05']],
                'hobbies'        => [['name' => 'Photographie', 'description' => '…']],
                'projects'       => [[
                    'title' => 'TechSolutions — Réseaux', 'published_on' => '2025-01-15', 'description' => '<p>Description du projet en <b>HTML</b>, en texte brut ou au format Editor.js…</p>',
                    'themes' => ['Réseau'], 'skills' => ['Cisco IOS'], 'links' => [['label' => 'Dépôt', 'url' => 'https://github.com/SMK027/exemple']],
                ]],
                'articles'       => [[
                    'title' => 'Titre de l\'article', 'excerpt' => 'Résumé…', 'content_html' => '<h2>Intro</h2><p>Texte…</p><img src="https://example.com/image.png">',
                    'author' => 'admin@app.local', 'themes' => ['Réseau'], 'is_pinned' => false, 'published_at' => '2025-03-01T10:00:00+01:00',
                ]],
                'announcements'  => [['title' => 'Je recherche une alternance', 'style' => 'success', 'link_url' => 'https://portfolio.leofranz.fr/contact']],
            ],
        ];
    }

    /* ======================================================================
     |  IMPORT
     * ==================================================================== */

    /**
     * Sections présentes dans un fichier, avec leur nombre d'éléments.
     *
     * @return array<string, int>
     */
    public function summarize(array $payload): array
    {
        $summary = [];
        foreach (array_keys(self::SECTIONS) as $section) {
            if (array_key_exists($section, $payload['sections'] ?? [])) {
                $value = $payload['sections'][$section];
                $summary[$section] = $section === 'profile' ? 1 : (is_array($value) ? count($value) : 0);
            }
        }

        return $summary;
    }

    /** Vérifie l'enveloppe du fichier ; retourne un message d'erreur ou null. */
    public function envelopeError(mixed $payload): ?string
    {
        if (! is_array($payload) || ! is_array($payload['sections'] ?? null)) {
            return 'Le fichier doit contenir un objet « sections » (voir le modèle).';
        }

        if (($payload['format'] ?? self::FORMAT) !== self::FORMAT) {
            return 'Format de fichier non reconnu.';
        }

        if ((int) ($payload['version'] ?? self::VERSION) > self::VERSION) {
            return 'Ce fichier provient d\'une version plus récente du portfolio.';
        }

        return null;
    }

    /**
     * Importe les sections demandées. En simulation, rien n'est enregistré.
     *
     * @param  list<string>  $sections
     * @return array<string, array{created: int, updated: int, skipped: int, errors: list<string>}>
     */
    public function import(array $payload, array $sections, bool $dryRun, User $importer, bool $downloadImages = false): array
    {
        $this->report = [];
        $this->importer = $importer;
        // Les images ne sont jamais téléchargées pendant une simulation.
        $this->downloadImages = $downloadImages && ! $dryRun;

        DB::beginTransaction();
        try {
            foreach (array_keys(self::SECTIONS) as $section) {
                if (! in_array($section, $sections, true) || ! array_key_exists($section, $payload['sections'])) {
                    continue;
                }

                $this->report[$section] = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
                $items = $section === 'profile' ? [$payload['sections'][$section]] : $payload['sections'][$section];

                if (! is_array($items) || ($section !== 'profile' && ! array_is_list($items))) {
                    $this->report[$section]['errors'][] = 'La section doit être une liste d\'éléments.';

                    continue;
                }

                foreach ($items as $index => $item) {
                    $label = $section === 'profile' ? 'présentation' : 'élément n°'.($index + 1);
                    if (! is_array($item)) {
                        $this->fail($section, $label, 'élément invalide');

                        continue;
                    }

                    try {
                        $this->{'import'.Str::studly($section)}($item, $label);
                    } catch (Throwable $e) {
                        $this->fail($section, $label, $e->getMessage());
                    }
                }
            }
        } finally {
            $dryRun ? DB::rollBack() : DB::commit();
            Page::flushCache();
        }

        return $this->report;
    }

    protected function importProfile(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'profile', [
            'first_name'          => ['nullable', 'string', 'max:100'],
            'last_name'           => ['nullable', 'string', 'max:100'],
            'headline'            => ['nullable', 'string', 'max:255'],
            'location'            => ['nullable', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'about'               => ['nullable'],
            'about_html'          => ['nullable', 'string'],
            'social_links'        => ['nullable', 'array'],
            'social_links.*.name' => ['nullable', 'string', 'max:50'],
            'social_links.*.url'  => ['required', 'url:http,https', 'max:255'],
        ]);
        if ($data === null) {
            return;
        }

        $values = collect($data)->except(['about', 'about_html'])->all();
        if (array_key_exists('about', $item) || array_key_exists('about_html', $item)) {
            $values['about'] = $this->editorContent($item['about'] ?? null, $item['about_html'] ?? null);
        }

        Profile::current()->update($values);
        $this->count('profile', false);
    }

    protected function importPages(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'pages', [
            'key'       => ['required', Rule::in(array_keys(Page::ROUTES))],
            'title'     => ['nullable', 'string', 'max:100'],
            'intro'     => ['nullable', 'string', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
            'position'  => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data === null) {
            return;
        }

        $page = Page::where('key', $data['key'])->first();
        if (! $page) {
            $this->fail('pages', $label, 'page « '.$data['key'].' » inconnue');

            return;
        }

        $page->update(collect($data)->except('key')->filter(fn ($v, $k) => array_key_exists($k, $item))->all());
        $this->count('pages', false);
    }

    protected function importThemes(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'themes', [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data === null) {
            return;
        }

        $theme = $this->findByName(Theme::query(), $data['name']) ?? new Theme;
        $this->save('themes', $theme, $data + ['position' => 0]);
    }

    protected function importSkills(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'skills', [
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['nullable', 'string', 'max:100'],
            'level'       => ['nullable', 'integer', 'min:1', 'max:'.Skill::MAX_LEVEL],
            'description' => ['nullable', 'string', 'max:500'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data === null) {
            return;
        }

        $skill = $this->findByName(Skill::query(), $data['name']) ?? new Skill;
        $this->save('skills', $skill, $data + ['position' => 0]);
    }

    protected function importEducations(array $item, string $label): void
    {
        $data = $this->validateDated($item, $label, 'educations', [
            'title'       => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['start_date' => true, 'end_date' => false]);
        if ($data === null) {
            return;
        }

        $model = Education::where('title', $data['title'])->where('institution', $data['institution'])->first() ?? new Education;
        $this->save('educations', $model, $data + ['position' => 0]);
    }

    protected function importExperiences(array $item, string $label): void
    {
        $data = $this->validateDated($item, $label, 'experiences', [
            'title'         => ['required', 'string', 'max:255'],
            'company'       => ['required', 'string', 'max:255'],
            'location'      => ['nullable', 'string', 'max:255'],
            'contract_type' => ['nullable', 'string', 'max:50'],
            'description'   => ['nullable'],
            'description_markdown' => ['nullable', 'string'],
            'position'      => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['start_date' => true, 'end_date' => false]);
        if ($data === null) {
            return;
        }

        // Missions : Markdown, contenu Editor.js, HTML ou texte brut.
        $markdown = $data['description_markdown'] ?? null;
        $data['description'] = filled($markdown)
            ? $this->markdownConverter->convert($markdown)
            : EditorContent::fromInput($data['description'] ?? null, $this->imageResolver());
        $data['description_editor'] = filled($markdown) ? 'markdown' : 'blocks';
        $data['description_markdown'] = filled($markdown) ? $markdown : null;

        $model = Experience::where('title', $data['title'])->where('company', $data['company'])
            ->whereDate('start_date', $data['start_date'])->first() ?? new Experience;
        $this->save('experiences', $model, $data + ['position' => 0]);
    }

    protected function importDiplomas(array $item, string $label): void
    {
        $data = $this->validateDated($item, $label, 'diplomas', [
            'title'       => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'level'       => ['nullable', 'string', 'max:50'],
            'mention'     => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['obtained_at' => true]);
        if ($data === null) {
            return;
        }

        $model = Diploma::where('title', $data['title'])->where('institution', $data['institution'])->first() ?? new Diploma;
        $this->save('diplomas', $model, $data + ['position' => 0]);
    }

    protected function importCertifications(array $item, string $label): void
    {
        $data = $this->validateDated($item, $label, 'certifications', [
            'name'           => ['required', 'string', 'max:255'],
            'issuer'         => ['nullable', 'string', 'max:255'],
            'credential_id'  => ['nullable', 'string', 'max:255'],
            'credential_url' => ['nullable', 'url:http,https', 'max:255'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'position'       => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['issued_at' => true, 'expires_at' => false]);
        if ($data === null) {
            return;
        }

        $model = Certification::where('name', $data['name'])
            ->where(fn ($q) => filled($data['issuer'] ?? null) ? $q->where('issuer', $data['issuer']) : $q->whereNull('issuer'))
            ->first() ?? new Certification;
        $this->save('certifications', $model, $data + ['position' => 0]);
    }

    protected function importHobbies(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'hobbies', [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data === null) {
            return;
        }

        $model = $this->findByName(Hobby::query(), $data['name']) ?? new Hobby;
        $this->save('hobbies', $model, $data + ['position' => 0]);
    }

    protected function importProjects(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'projects', [
            'title'         => ['required', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255'],
            'published_on'  => ['required', 'date'],
            'description'          => ['required_without:description_markdown'],
            'description_markdown' => ['nullable', 'string'],
            'themes'        => ['nullable', 'array'],
            'themes.*'      => ['string', 'max:100'],
            'skills'        => ['nullable', 'array'],
            'skills.*'      => ['string', 'max:100'],
            'links'         => ['nullable', 'array', 'max:20'],
            'links.*.label' => ['nullable', 'string', 'max:100'],
            'links.*.url'   => ['required', 'url:http,https', 'max:2048'],
        ]);
        if ($data === null) {
            return;
        }

        // Description : Markdown, contenu Editor.js, HTML (converti, images récupérées) ou texte brut.
        $markdown = $data['description_markdown'] ?? null;
        $description = filled($markdown)
            ? $this->markdownConverter->convert($markdown)
            : EditorContent::fromInput($data['description'] ?? null, $this->imageResolver());
        if (EditorContent::isEmpty($description)) {
            $this->fail('projects', $label.' « '.$data['title'].' »', 'description vide');

            return;
        }

        $project = (filled($data['slug'] ?? null) ? Project::where('slug', $data['slug'])->first() : null)
            ?? Project::where('title', $data['title'])->first()
            ?? new Project;

        $created = ! $project->exists;
        $project->fill([
            'title'        => $data['title'],
            'published_on' => Carbon::parse($data['published_on'])->toDateString(),
            'description'          => $description,
            'description_editor'   => filled($markdown) ? 'markdown' : 'blocks',
            'description_markdown' => filled($markdown) ? $markdown : null,
        ])->save();

        $project->themes()->sync($this->themeIds($data['themes'] ?? [], 'projects'));
        $project->skills()->sync($this->skillIds($data['skills'] ?? []));

        if (array_key_exists('links', $data)) {
            $project->links()->delete();
            foreach (array_values($data['links'] ?? []) as $i => $link) {
                $project->links()->create(['label' => $link['label'] ?? null, 'url' => $link['url'], 'position' => $i]);
            }
        }

        $this->count('projects', $created);
    }

    protected function importArticles(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'articles', [
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => ['nullable', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'content'      => ['nullable'],
            'content_html' => ['nullable', 'string'],
            'content_markdown' => ['nullable', 'string'],
            'author'       => ['nullable', 'email'],
            'coauthors'    => ['nullable', 'array'],
            'coauthors.*'  => ['email'],
            'themes'       => ['nullable', 'array'],
            'themes.*'     => ['string', 'max:100'],
            'is_pinned'    => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
        if ($data === null) {
            return;
        }

        $article = (filled($data['slug'] ?? null) ? Article::where('slug', $data['slug'])->first() : null)
            ?? Article::where('title', $data['title'])->first()
            ?? new Article;
        $created = ! $article->exists;

        $author = filled($data['author'] ?? null) ? User::where('email', $data['author'])->first() : null;
        if (filled($data['author'] ?? null) && ! $author) {
            $this->warn('articles', $label, 'auteur « '.$data['author'].' » inconnu : attribué à '.$this->importer->email);
        }

        $article->fill([
            'title'        => $data['title'],
            'excerpt'      => $data['excerpt'] ?? null,
            'content'          => filled($data['content_markdown'] ?? null)
                ? $this->markdownConverter->convert($data['content_markdown'])
                : $this->editorContent($data['content'] ?? null, $data['content_html'] ?? null),
            'content_editor'   => filled($data['content_markdown'] ?? null) ? 'markdown' : 'blocks',
            'content_markdown' => filled($data['content_markdown'] ?? null) ? $data['content_markdown'] : null,
            'author_id'    => ($author ?? ($article->author_id ? null : $this->importer))?->id ?? $article->author_id,
            'is_pinned'    => (bool) ($data['is_pinned'] ?? false),
            'published_at' => filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : null,
        ])->save();

        $coauthors = collect($data['coauthors'] ?? [])->map(function ($email) use ($label) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->warn('articles', $label, 'co-auteur « '.$email.' » inconnu : ignoré (créez d\'abord son compte)');
            }

            return $user?->id;
        })->filter()->reject(fn ($id) => $id === $article->author_id)->values()->all();

        $article->coauthors()->sync($coauthors);
        $article->themes()->sync($this->themeIds($data['themes'] ?? [], 'articles'));

        $this->count('articles', $created);
    }

    protected function importAnnouncements(array $item, string $label): void
    {
        $data = $this->validate($item, $label, 'announcements', [
            'title'          => ['required', 'string', 'max:150'],
            'message'        => ['nullable', 'string', 'max:500'],
            'style'          => ['nullable', Rule::in(array_keys(Announcement::STYLES))],
            'link_url'       => ['nullable', 'url:http,https', 'max:2048'],
            'link_label'     => ['nullable', 'string', 'max:60'],
            'is_active'      => ['nullable', 'boolean'],
            'is_dismissible' => ['nullable', 'boolean'],
            'starts_at'      => ['nullable', 'date'],
            'ends_at'        => ['nullable', 'date'],
            'position'       => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data === null) {
            return;
        }

        $model = Announcement::where('title', $data['title'])->first() ?? new Announcement;
        $this->save('announcements', $model, [
            ...$data,
            'style'          => $data['style'] ?? 'primary',
            'is_active'      => (bool) ($data['is_active'] ?? true),
            'is_dismissible' => (bool) ($data['is_dismissible'] ?? true),
            'position'       => (int) ($data['position'] ?? 0),
        ]);
    }

    /* ----------------------------------------------------------------------
     |  Outils
     * -------------------------------------------------------------------- */

    /** @return array<string, mixed>|null */
    protected function validate(array $item, string $label, string $section, array $rules): ?array
    {
        $validator = Validator::make($item, $rules);
        if ($validator->fails()) {
            $this->fail($section, $label.(isset($item['title']) || isset($item['name']) ? ' « '.($item['title'] ?? $item['name']).' »' : ''), $validator->errors()->first());

            return null;
        }

        return $validator->validated();
    }

    /**
     * Validation avec dates à précision variable : « 2024 », « 2024-09 » ou « 2024-09-15 ».
     * La précision est déduite du format si date_precision est absent.
     *
     * @param  array<string, bool>  $dateFields
     */
    protected function validateDated(array $item, string $label, string $section, array $rules, array $dateFields): ?array
    {
        // Une année peut être fournie comme nombre (2024) : on travaille sur des chaînes.
        foreach (array_keys($dateFields) as $field) {
            if (isset($item[$field]) && is_int($item[$field])) {
                $item[$field] = (string) $item[$field];
            }
        }

        $first = (string) ($item[array_key_first($dateFields)] ?? '');
        $precision = $item['date_precision'] ?? match (true) {
            (bool) preg_match('/^\d{4}$/', $first)       => PreciseDate::YEAR,
            (bool) preg_match('/^\d{4}-\d{2}$/', $first) => PreciseDate::MONTH,
            default                                      => PreciseDate::DAY,
        };

        foreach ($dateFields as $field => $required) {
            $rules[$field] = [$required ? 'required' : 'nullable', 'string'];
            // Les dates complètes (« 2024-09-15 » ou ISO) sont acceptées quelle que soit la précision.
            if (isset($item[$field]) && $precision !== PreciseDate::DAY && strlen((string) $item[$field]) > 7) {
                $item[$field] = substr((string) $item[$field], 0, $precision === PreciseDate::YEAR ? 4 : 7);
            }
        }
        $rules['date_precision'] = ['nullable', Rule::in(array_keys(PreciseDate::LABELS))];

        $data = $this->validate($item, $label, $section, $rules);
        if ($data === null) {
            return null;
        }

        $formatCheck = Validator::make($item, collect($dateFields)->map(fn ($required) => ['nullable', PreciseDate::rule($precision)])->all(), [
            'regex' => 'Le champ :attribute doit être une année sur 4 chiffres.',
        ]);
        if ($formatCheck->fails()) {
            $this->fail($section, $label, $formatCheck->errors()->first());

            return null;
        }

        $data['date_precision'] = $precision;
        foreach (array_keys($dateFields) as $field) {
            $data[$field] = PreciseDate::parse($item[$field] ?? null, $precision);
        }

        return $data;
    }

    protected function save(string $section, Model $model, array $values): void
    {
        $created = ! $model->exists;
        $model->fill($values)->save();
        $this->count($section, $created);
    }

    protected function findByName($query, string $name): ?Model
    {
        return $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }

    /** @return list<int> */
    protected function themeIds(array $names, string $section): array
    {
        return collect($names)->filter()->map(function ($name) {
            $theme = $this->findByName(Theme::query(), $name);
            if (! $theme) {
                $theme = Theme::create(['name' => trim($name), 'position' => (int) Theme::max('position') + 1]);
                $this->count('themes', true, autoCreated: true);
            }

            return $theme->id;
        })->unique()->values()->all();
    }

    /** @return list<int> */
    protected function skillIds(array $names): array
    {
        return collect($names)->filter()->map(function ($name) {
            $skill = $this->findByName(Skill::query(), $name);
            if (! $skill) {
                $skill = Skill::create(['name' => trim($name), 'position' => 0]);
                $this->count('skills', true, autoCreated: true);
            }

            return $skill->id;
        })->unique()->values()->all();
    }

    /**
     * Contenu Editor.js : objet { blocks } fourni tel quel, sinon HTML converti.
     *
     * @return array<string, mixed>|null
     */
    protected function editorContent(mixed $json, ?string $html): ?array
    {
        // Une chaîne est soit du JSON Editor.js, soit du HTML.
        if (is_string($json)) {
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                $html ??= $json;
            }
            $json = is_array($decoded) ? $decoded : null;
        }

        if (is_array($json) && is_array($json['blocks'] ?? null)) {
            return ['time' => $json['time'] ?? null, 'blocks' => array_values($json['blocks']), 'version' => $json['version'] ?? null];
        }

        if (blank($html)) {
            return null;
        }

        return $this->htmlConverter->withImageResolver($this->imageResolver())->convert($html);
    }

    /** Récupération des images distantes sur le site (si demandée). */
    protected function imageResolver(): ?callable
    {
        return $this->downloadImages
            ? function (string $src): ?string {
                try {
                    return $this->imageFetcher->fetch($src, 'editor/'.now()->format('Y/m'));
                } catch (Throwable) {
                    return null; // on garde l'adresse d'origine
                }
            }
            : null;
    }

    protected function count(string $section, bool $created, bool $autoCreated = false): void
    {
        $this->report[$section] ??= ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $this->report[$section][$created ? 'created' : 'updated']++;
        if ($autoCreated) {
            $this->report[$section]['auto_created'] = ($this->report[$section]['auto_created'] ?? 0) + 1;
        }
    }

    protected function fail(string $section, string $label, string $message): void
    {
        $this->report[$section]['skipped']++;
        $this->report[$section]['errors'][] = ucfirst($label).' : '.$message;
    }

    protected function warn(string $section, string $label, string $message): void
    {
        $this->report[$section]['errors'][] = ucfirst($label).' : '.$message;
    }
}
