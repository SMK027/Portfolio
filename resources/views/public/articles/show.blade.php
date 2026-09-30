<x-public-layout :page="$page" :title="$article->title" :description="$article->excerpt" :image="$article->thumbnailUrl()" type="article">
    @push('head')
        <meta property="article:published_time" content="{{ $article->published_at?->toAtomString() }}">
        <meta property="article:modified_time" content="{{ $article->updated_at?->toAtomString() }}">
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context'      => 'https://schema.org',
            '@type'         => 'BlogPosting',
            'headline'      => \Illuminate\Support\Str::limit($article->title, 110),
            'description'   => $article->excerpt,
            'image'         => $article->thumbnailUrl() ? url($article->thumbnailUrl()) : null,
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified'  => $article->updated_at?->toAtomString(),
            'author'        => array_values(array_filter([$article->author, ...$article->coauthors]))
                ? collect([$article->author, ...$article->coauthors])->filter()->map(fn ($u) => ['@type' => 'Person', 'name' => $u->name])->values()->all() : null,
            'mainEntityOfPage' => route('articles.show', $article),
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush
    <article>
        <header class="relative overflow-hidden bg-slate-900">
            @if ($article->thumbnailUrl())
                <img src="{{ $article->thumbnailUrl() }}" alt="" fetchpriority="high" decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-30">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/70 to-slate-900/40"></div>
            @else
                <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900"></div>
            @endif
            <div class="relative mx-auto max-w-3xl px-4 py-14 sm:px-6 sm:py-20">
                <a href="{{ route('articles.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-300 hover:text-white">
                    <x-icon name="arrow-left" class="h-4 w-4" /> {{ $page->title }}
                </a>

                @unless ($article->isPublished())
                    <p class="mt-4"><span class="badge-amber">{{ $article->status() }} — aperçu administrateur</span></p>
                @endunless

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($article->is_pinned)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-semibold text-amber-950"><x-icon name="pin" class="h-3.5 w-3.5" /> Épinglé</span>
                    @endif
                    @foreach ($article->themes as $theme)
                        <a href="{{ route('articles.index', ['theme' => $theme->slug]) }}" class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-medium text-white hover:bg-white/20">{{ $theme->name }}</a>
                    @endforeach
                </div>

                <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-white sm:text-5xl">{{ $article->title }}</h1>
                @if ($article->excerpt)
                    <p class="mt-4 text-lg text-slate-300">{{ $article->excerpt }}</p>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-300">
                    <span>Par <strong class="text-white">{{ $article->author?->name }}</strong>
                        @if ($article->coauthors->isNotEmpty())
                            avec <strong class="text-white">{{ $article->coauthors->pluck('name')->join(', ', ' et ') }}</strong>
                        @endif
                    </span>
                    @if ($article->published_at)
                        <time datetime="{{ $article->published_at->toIso8601String() }}" class="inline-flex items-center gap-1"><x-icon name="calendar" class="h-4 w-4" /> {{ $article->published_at->translatedFormat('j F Y') }}</time>
                    @endif
                    <span class="inline-flex items-center gap-1"><x-icon name="clock" class="h-4 w-4" /> {{ $article->readingTime() }} min de lecture</span>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-3xl space-y-10 px-4 py-12 sm:px-6">
            @if ($article->images()->isNotEmpty())
                <x-carousel :images="$article->images()" :title="$article->title" label="Images de l'article" />
            @endif

            <div class="editor-content prose-lg">@editorjs($article->content)</div>

            @if ($article->documents()->isNotEmpty())
                <section>
                    <h2 class="mb-4 font-display text-xl font-semibold text-slate-900">Documents</h2>
                    <x-file-list :documents="$article->documents()" />
                </section>
            @endif

            @auth
                @if (auth()->user()->isAdmin())
                    <div class="border-t border-slate-200 pt-6">
                        <a href="{{ route('admin.articles.edit', $article) }}" class="btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Modifier cet article</a>
                    </div>
                @endif
            @endauth
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 sm:px-6">
            <h2 class="mb-5 font-display text-2xl font-bold text-slate-900">Sur le même thème</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-article-card :article="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-public-layout>
