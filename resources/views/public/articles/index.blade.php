<x-public-layout :page="$page" :title="$theme ? $page->title.' — '.$theme->name : $page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        @if ($themes->isNotEmpty())
            <nav class="mb-10 flex flex-wrap gap-2" aria-label="Filtrer par thème">
                <a href="{{ route('articles.index') }}" @class([
                    'rounded-full border px-3 py-1 text-sm transition',
                    'border-primary-500 bg-primary-50 font-medium text-primary-700' => ! $theme,
                    'border-slate-300 bg-white text-slate-600 hover:border-primary-300' => $theme,
                ])>Tous</a>
                @foreach ($themes as $item)
                    <a href="{{ route('articles.index', ['theme' => $item->slug]) }}" @class([
                        'rounded-full border px-3 py-1 text-sm transition',
                        'border-primary-500 bg-primary-50 font-medium text-primary-700' => $theme?->is($item),
                        'border-slate-300 bg-white text-slate-600 hover:border-primary-300' => ! $theme?->is($item),
                    ])>{{ $item->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($pinned->isNotEmpty() && $articles->onFirstPage())
            <section class="mb-12">
                <h2 class="mb-5 flex items-center gap-2 font-display text-xl font-bold text-slate-900">
                    <x-icon name="pin" class="h-5 w-5 text-amber-500" /> À la une
                </h2>
                <div class="grid gap-6">
                    @foreach ($pinned as $article)
                        <x-article-card :article="$article" featured />
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            @if ($pinned->isNotEmpty() && $articles->onFirstPage())
                <h2 class="mb-5 font-display text-xl font-bold text-slate-900">Derniers articles</h2>
            @endif

            @if ($articles->isEmpty() && $pinned->isEmpty())
                <x-empty-state icon="newspaper" message="Aucun article publié pour le moment." />
            @elseif ($articles->isNotEmpty())
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($articles as $article)
                        <x-article-card :article="$article" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $articles->links() }}</div>
            @endif
        </section>
    </div>
</x-public-layout>
