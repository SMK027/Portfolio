<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    use HandlesUploads;

    public function index(Request $request): View
    {
        return view('admin.articles.index', [
            'articles' => Article::with('author', 'coauthors', 'themes')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->orderByDesc('is_pinned')
                ->orderByRaw('published_at IS NULL DESC')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.articles.form', $this->formData(new Article(['author_id' => $request->user()->id])));
    }

    public function store(Request $request): RedirectResponse
    {
        $article = DB::transaction(fn () => $this->save($request, new Article));

        return redirect()->route('admin.articles.edit', $article)->with('success', 'Article créé.');
    }

    public function edit(Article $article): View
    {
        $article->load('coauthors', 'themes');

        return view('admin.articles.form', $this->formData($article));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        DB::transaction(fn () => $this->save($request, $article));

        return redirect()->route('admin.articles.edit', $article->fresh())->with('success', 'Article mis à jour.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->deletePublicFile($article->thumbnail_path);
        $article->delete();

        return redirect()->route('admin.articles.index')->with('success', 'Article supprimé.');
    }

    /** @return array<string, mixed> */
    protected function formData(Article $article): array
    {
        return [
            'article' => $article,
            'users'   => User::orderBy('name')->get(),
            'themes'  => Theme::ordered()->get(),
        ];
    }

    protected function save(Request $request, Article $article): Article
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'content'      => ['nullable', 'string', 'max:2000000'],
            'author_id'    => ['required', 'integer', 'exists:users,id'],
            'coauthors'    => ['nullable', 'array'],
            'coauthors.*'  => ['integer', 'exists:users,id', Rule::notIn([(int) $request->input('author_id')])],
            'themes'       => ['nullable', 'array'],
            'themes.*'     => ['integer', 'exists:themes,id'],
            'is_pinned'    => ['nullable', 'boolean'],
            'status'       => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'thumbnail'    => $this->imageRules(),
        ], [
            'coauthors.*.not_in' => 'L\'auteur principal ne peut pas être aussi co-auteur.',
        ]);

        $publishedAt = null;
        if ($data['status'] === 'published') {
            // Sans date, l'article est publié immédiatement ; une date future le programme.
            $publishedAt = filled($data['published_at'] ?? null)
                ? Carbon::parse($data['published_at'])
                : ($article->published_at ?? now());
        }

        $article->fill([
            'title'          => $data['title'],
            'excerpt'        => $data['excerpt'] ?? null,
            'content'        => $this->editorContent($request, 'content'),
            'author_id'      => $data['author_id'],
            'is_pinned'      => $request->boolean('is_pinned'),
            'published_at'   => $publishedAt,
            'thumbnail_path' => $this->syncPublicFile($request, 'thumbnail', $article->thumbnail_path, 'articles'),
        ])->save();

        $article->coauthors()->sync($data['coauthors'] ?? []);
        $article->themes()->sync($data['themes'] ?? []);

        return $article;
    }
}
