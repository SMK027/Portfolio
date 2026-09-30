<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesRichText;
use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\StoresAttachments;
use App\Http\Controllers\Controller;
use App\Mail\ArticleReviewNotification;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\Theme;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Articles de veille — administrateurs et contributeurs (voir ArticlePolicy).
 */
class ArticleController extends Controller
{
    use HandlesRichText, HandlesUploads, StoresAttachments;

    public function __construct(protected SafeMailer $mailer, protected AuditTrail $audit)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Article::class);
        $user = $request->user();
        $filter = $request->string('filtre')->toString();

        return view('admin.articles.index', [
            'filter'       => $filter,
            'pendingCount' => Article::pendingReview()->count(),
            'articles'     => Article::with('author', 'coauthors', 'themes')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->when($filter === 'a-valider', fn ($q) => $q->pendingReview())
                ->when($filter === 'mes-articles', fn ($q) => $q->where(fn ($q) => $q
                    ->where('author_id', $user->id)
                    ->orWhereHas('coauthors', fn ($c) => $c->whereKey($user->id))))
                ->orderByRaw('CASE WHEN review_status = ? AND published_at IS NULL THEN 0 ELSE 1 END', [Article::REVIEW_PENDING])
                ->orderByDesc('is_pinned')
                ->orderByRaw('published_at IS NULL DESC')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    /** Consultation en lecture seule (tous les articles, brouillons compris). */
    public function show(Article $article): View
    {
        Gate::authorize('view', $article);
        $article->load('author', 'coauthors', 'themes', 'files', 'reviewer');

        return view('admin.articles.show', ['article' => $article]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Article::class);

        return view('admin.articles.form', $this->formData(new Article(['author_id' => $request->user()->id])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Article::class);
        $article = DB::transaction(fn () => $this->save($request, new Article));

        return redirect()->route('admin.articles.edit', $article)->with('success', $this->savedMessage($request, 'Article créé.'));
    }

    public function edit(Article $article): View
    {
        Gate::authorize('update', $article);
        $article->load('coauthors', 'themes', 'files', 'reviewer');

        return view('admin.articles.form', $this->formData($article));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);
        DB::transaction(fn () => $this->save($request, $article));

        return redirect()->route('admin.articles.edit', $article->fresh())->with('success', $this->savedMessage($request, 'Article mis à jour.'));
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $this->deletePublicFile($article->thumbnail_path);
        $article->delete();

        return redirect()->route('admin.articles.index')->with('success', 'Article supprimé.');
    }

    /** Validation par un administrateur : publication de l'article soumis. */
    public function approve(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('review', $article);
        $data = $request->validate(['published_at' => ['nullable', 'date']]);

        $article->update([
            'published_at'  => filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : now(),
            'review_status' => null,
            'review_note'   => null,
            'reviewed_by'   => $request->user()->id,
            'reviewed_at'   => now(),
        ]);

        $this->audit->record('article.approved', $article, meta: ['published_at' => $article->published_at?->toDateTimeString()]);
        $this->notifyAuthor($article, ArticleReviewNotification::APPROVED);

        return back()->with('success', $article->isPublished() ? 'Article validé et publié.' : 'Article validé : publication programmée.');
    }

    /** Renvoi en brouillon avec un commentaire pour l'auteur. */
    public function requestChanges(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('review', $article);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']], [], ['review_note' => 'commentaire']);

        $article->update([
            'published_at'  => null,
            'review_status' => Article::REVIEW_CHANGES_REQUESTED,
            'review_note'   => $data['review_note'],
            'reviewed_by'   => $request->user()->id,
            'reviewed_at'   => now(),
        ]);

        $this->audit->record('article.changes_requested', $article, meta: ['commentaire' => $data['review_note']]);
        $this->notifyAuthor($article, ArticleReviewNotification::CHANGES_REQUESTED);

        return back()->with('success', 'Article renvoyé en brouillon à son auteur.');
    }

    /** @return array<string, mixed> */
    protected function formData(Article $article): array
    {
        return [
            'article' => $article,
            'users'   => User::humans()->orderBy('name')->get(),
            'themes'  => Theme::ordered()->get(),
        ];
    }

    protected function save(Request $request, Article $article): Article
    {
        $user = $request->user();
        $canPublish = Gate::allows('publish', Article::class);
        $relationsBefore = $this->relations($article);
        $authorId = $canPublish ? (int) $request->input('author_id') : ($article->author_id ?? $user->id);

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'coauthors'    => ['nullable', 'array'],
            'coauthors.*'  => ['integer', 'exists:users,id', Rule::notIn([$authorId])],
            'themes'       => ['nullable', 'array'],
            'themes.*'     => ['integer', 'exists:themes,id'],
            'thumbnail'    => $this->imageRules(),
            ...$this->attachmentRules(ArticleFile::class),
            ...($canPublish ? [
                'author_id'    => ['required', 'integer', 'exists:users,id'],
                'is_pinned'    => ['nullable', 'boolean'],
                'status'       => ['required', Rule::in(['draft', 'published'])],
                'published_at' => ['nullable', 'date'],
            ] : [
                'intent'       => ['nullable', Rule::in(['draft', 'submit'])],
            ]),
        ], [
            'coauthors.*.not_in' => 'L\'auteur principal ne peut pas être aussi co-auteur.',
        ]);

        $values = [
            'title'          => $data['title'],
            'excerpt'        => $data['excerpt'] ?? null,
            ...$this->richText($request, 'content', false, 'contenu'),
            'author_id'      => $authorId,
            'thumbnail_path' => $this->syncPublicFile($request, 'thumbnail', $article->thumbnail_path, 'articles'),
        ];

        $submitted = false;

        if ($canPublish) {
            // Administrateur : publication directe (une date future programme l'article).
            $publishedAt = $data['status'] === 'published'
                ? (filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : ($article->published_at ?? now()))
                : null;

            $values += [
                'is_pinned'    => $request->boolean('is_pinned'),
                'published_at' => $publishedAt,
            ];

            if ($publishedAt && $article->review_status) {
                $values += ['review_status' => null, 'reviewed_by' => $user->id, 'reviewed_at' => now()];
            }
        } elseif ($article->published_at === null) {
            // Contributeur : brouillon uniquement ; il peut le soumettre à validation.
            if (($data['intent'] ?? 'draft') === 'submit') {
                $values += ['review_status' => Article::REVIEW_PENDING, 'submitted_at' => now()];
                $submitted = $article->review_status !== Article::REVIEW_PENDING;
            } elseif ($article->review_status === Article::REVIEW_PENDING) {
                $values += ['review_status' => null]; // demande de validation retirée
            }
        }

        $article->fill($values)->save();

        // Un contributeur co-auteur reste co-auteur (il garde ainsi l'accès à l'article).
        $coauthors = collect($data['coauthors'] ?? [])->map(fn ($id) => (int) $id);
        if (! $canPublish && $article->author_id !== $user->id) {
            $coauthors->push($user->id);
        }
        $article->coauthors()->sync($coauthors->unique()->reject(fn ($id) => $id === $article->author_id)->values()->all());
        $article->themes()->sync($data['themes'] ?? []);

        $this->syncAttachments($request, $article->files(), ArticleFile::class, 'articles/'.$article->id);

        $this->audit->recordRelations($article, $relationsBefore, $this->relations($article->fresh()));

        if ($submitted) {
            $this->audit->record('article.submitted', $article);
            $this->notifyAdmins($article);
        }

        return $article;
    }

    /** @return array<string, list<string>> */
    protected function relations(Article $article): array
    {
        if (! $article->exists) {
            return ['thèmes' => [], 'co-auteurs' => []];
        }

        return [
            'thèmes'     => $article->themes()->pluck('name')->all(),
            'co-auteurs' => $article->coauthors()->pluck('name')->all(),
        ];
    }

    protected function savedMessage(Request $request, string $default): string
    {
        return ! Gate::allows('publish', Article::class) && $request->input('intent') === 'submit'
            ? 'Article soumis à validation : un administrateur va le relire.'
            : $default;
    }

    protected function notifyAdmins(Article $article): void
    {
        User::whereIn('global_role', ['admin', 'superadmin'])->pluck('email')
            ->each(fn ($email) => $this->mailer->send($email, new ArticleReviewNotification($article->fresh(['author']), ArticleReviewNotification::SUBMITTED), 'article soumis à validation'));
    }

    protected function notifyAuthor(Article $article, string $event): void
    {
        $article->load('author', 'reviewer');
        if ($article->author) {
            $this->mailer->send($article->author->email, new ArticleReviewNotification($article, $event), 'décision de validation d\'article');
        }
    }
}
