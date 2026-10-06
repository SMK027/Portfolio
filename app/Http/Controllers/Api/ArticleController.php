<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesContent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Mail\ArticleReviewNotification;
use App\Models\Article;
use App\Models\Theme;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\SafeMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    use ResolvesContent;

    public function __construct(protected AuditTrail $audit, protected SafeMailer $mailer)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status'   => ['nullable', Rule::in(['draft', 'pending_review', 'scheduled', 'published'])],
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $articles = Article::with('author', 'coauthors', 'themes')
            ->when($request->input('q'), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->when($request->input('status'), fn ($q, $status) => match ($status) {
                'published'      => $q->published(),
                'scheduled'      => $q->where('published_at', '>', now()),
                'pending_review' => $q->pendingReview(),
                default          => $q->whereNull('published_at'),
            })
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20));

        return ArticleResource::collection($articles);
    }

    public function show(Article $article): ArticleResource
    {
        return new ArticleResource($article->load('author', 'coauthors', 'themes'));
    }

    public function store(Request $request): JsonResponse
    {
        $article = DB::transaction(fn () => $this->save($request, new Article));

        return (new ArticleResource($article->load('author', 'coauthors', 'themes')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Article $article): ArticleResource
    {
        DB::transaction(fn () => $this->save($request, $article));

        return new ArticleResource($article->fresh(['author', 'coauthors', 'themes']));
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(null, 204);
    }

    public function publish(Request $request, Article $article): ArticleResource
    {
        $data = $request->validate(['published_at' => ['nullable', 'date']]);

        $article->update([
            'published_at'  => filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : now(),
            'review_status' => null,
            'reviewed_by'   => $request->user()->id,
            'reviewed_at'   => now(),
        ]);

        return new ArticleResource($article->fresh(['author', 'coauthors', 'themes']));
    }

    public function unpublish(Article $article): ArticleResource
    {
        $article->update(['published_at' => null]);

        return new ArticleResource($article->fresh(['author', 'coauthors', 'themes']));
    }

    protected function save(Request $request, Article $article): Article
    {
        $account = $request->user();
        $creating = ! $article->exists;

        $data = $request->validate([
            'title'             => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'excerpt'           => ['nullable', 'string', 'max:500'],
            'content'           => ['nullable', 'array'],
            'content_markdown'  => ['nullable', 'string', 'max:1000000'],
            'content_html'      => ['nullable', 'string', 'max:2000000'],
            'themes'            => ['nullable', 'array'],
            'themes.*'          => ['string', 'max:100'],
            'author'            => ['nullable', 'string', 'max:255'],
            'coauthors'         => ['nullable', 'array'],
            'coauthors.*'       => ['email'],
            'status'            => ['nullable', Rule::in(['draft', 'published'])],
            'published_at'      => ['nullable', 'date'],
            'is_pinned'         => ['nullable', 'boolean'],
            'submit_for_review' => ['nullable', 'boolean'],
        ]);

        // Publication, programmation et épinglage : autorisation dédiée.
        $publishing = $request->hasAny(['status', 'published_at', 'is_pinned']);
        if ($publishing && ! $account->hasServicePermission('articles.publish')) {
            $this->audit->record('api.forbidden', $article->exists ? $article : null, meta: [
                'autorisation' => 'articles.publish', 'route' => $request->method().' '.$request->path(),
            ], force: true);
            abort(403, 'Autorisation manquante : articles.publish (publication, programmation, épinglage).');
        }

        $before = $creating ? ['thèmes' => [], 'co-auteurs' => []] : [
            'thèmes' => $article->themes()->pluck('name')->all(), 'co-auteurs' => $article->coauthors()->pluck('name')->all(),
        ];

        $values = collect($data)->only(['title', 'excerpt'])->all();
        $values += $this->richTextInput($request, 'content') ?? [];

        if (filled($data['author'] ?? null)) {
            $values['author_id'] = $this->authorAccount($data['author'])->id;
        } elseif ($creating) {
            $values['author_id'] = $account->id;
        }

        if ($publishing) {
            if (array_key_exists('is_pinned', $data)) {
                $values['is_pinned'] = (bool) $data['is_pinned'];
            }
            if (($data['status'] ?? null) === 'draft') {
                $values['published_at'] = null;
            } elseif (($data['status'] ?? null) === 'published' || filled($data['published_at'] ?? null)) {
                $values['published_at'] = filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : ($article->published_at ?? now());
                $values['review_status'] = null;
            }
        }

        $submitted = ($data['submit_for_review'] ?? false) && ($values['published_at'] ?? $article->published_at) === null;
        if ($submitted) {
            $values += ['review_status' => Article::REVIEW_PENDING, 'submitted_at' => now()];
        }

        $article->fill($values)->save();

        if (array_key_exists('themes', $data)) {
            $article->themes()->sync($this->idsByName(Theme::class, $data['themes'] ?? [], 'themes'));
        }
        if (array_key_exists('coauthors', $data)) {
            $article->coauthors()->sync(collect($data['coauthors'] ?? [])
                ->map(fn ($email) => $this->humanByEmail($email, 'coauthors')->id)
                ->reject(fn ($id) => $id === $article->author_id)->unique()->values()->all());
        }

        $this->audit->recordRelations($article, $before, [
            'thèmes' => $article->themes()->pluck('name')->all(), 'co-auteurs' => $article->coauthors()->pluck('name')->all(),
        ]);

        if ($submitted) {
            $this->audit->record('article.submitted', $article);
            User::whereIn('global_role', ['admin', 'superadmin'])->pluck('email')->each(fn ($email) => $this->mailer->queue(
                $email, new ArticleReviewNotification($article->fresh(['author']), ArticleReviewNotification::SUBMITTED), 'article soumis à validation'
            ));
        }

        return $article;
    }
}
