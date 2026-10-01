<x-app-layout>
    <x-slot name="title">{{ $article->title }}</x-slot>
    <x-slot name="header">{{ $article->title }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.articles.index') }}" class="btn-secondary hidden sm:inline-flex"><x-icon name="arrow-left" class="h-4 w-4" /> Retour</a>
        <a href="{{ route('admin.articles.revisions.index', $article) }}" class="btn-secondary"><x-icon name="clock" class="h-4 w-4" /> Historique</a>
        @can('update', $article)
            <a href="{{ route('admin.articles.edit', $article) }}" class="btn-primary"><x-icon name="pencil" class="h-4 w-4" /> Modifier</a>
        @endcan
    </x-slot>

    @include('admin.articles.partials.review')
    @include('admin.articles.partials.review-note')
    @include('admin.articles.partials.preview-link')

    <article class="card overflow-hidden">
        @if ($article->thumbnailUrl())
            <img src="{{ $article->thumbnailUrl() }}" alt="" class="aspect-[21/9] w-full object-cover">
        @endif
        <div class="space-y-6 p-4 sm:p-8">
            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-article-status :article="$article" />
                @if ($article->published_at)<span>{{ $article->published_at->translatedFormat('j F Y à H:i') }}</span>@endif
                <span>· Par <strong class="text-slate-700">{{ $article->author?->name }}</strong>@if ($article->coauthors->isNotEmpty()) avec {{ $article->coauthors->pluck('name')->join(', ', ' et ') }}@endif</span>
                @foreach ($article->themes as $theme)<span class="badge-primary">{{ $theme->name }}</span>@endforeach
            </div>

            @if ($article->excerpt)<p class="text-lg text-slate-600">{{ $article->excerpt }}</p>@endif

            @if ($article->images()->isNotEmpty())
                <x-carousel :images="$article->images()" :title="$article->title" label="Images de l'article" />
            @endif

            <div class="editor-content">@editorjs($article->content)</div>

            @if ($article->documents()->isNotEmpty())
                <section>
                    <h2 class="mb-4 font-display text-lg font-semibold text-slate-900">Documents</h2>
                    <x-file-list :documents="$article->documents()" />
                </section>
            @endif
        </div>
    </article>
</x-app-layout>
