@props(['article', 'featured' => false])
<article @class(['group card relative flex overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg', 'flex-col md:flex-row' => $featured, 'flex-col' => ! $featured])>
    <div @class(['relative overflow-hidden bg-gradient-to-br from-slate-200 to-primary-100', 'aspect-[16/9] md:aspect-auto md:w-2/5' => $featured, 'aspect-[16/9]' => ! $featured])>
        @if ($article->thumbnailUrl())
            <img src="{{ $article->thumbnailUrl() }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <span class="absolute inset-0 flex items-center justify-center text-primary-300"><x-icon name="newspaper" class="h-12 w-12" /></span>
        @endif
        @if ($article->is_pinned)
            <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-1 text-xs font-semibold text-amber-950 shadow">
                <x-icon name="pin" class="h-3.5 w-3.5" /> Épinglé
            </span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5 {{ $featured ? 'md:p-7' : '' }}">
        <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time>
            @endif
            <span class="inline-flex items-center gap-1"><x-icon name="clock" class="h-3.5 w-3.5" /> {{ $article->readingTime() }} min</span>
            @foreach ($article->themes->take(2) as $theme)
                <span class="badge-primary">{{ $theme->name }}</span>
            @endforeach
        </div>
        <h3 @class(['font-display font-semibold text-slate-900', 'text-2xl' => $featured, 'text-lg' => ! $featured])>
            <a href="{{ route('articles.show', $article) }}" class="after:absolute after:inset-0 focus:outline-none">{{ $article->title }}</a>
        </h3>
        @if ($article->excerpt)
            <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $article->excerpt }}</p>
        @endif
        <p class="mt-auto pt-4 text-xs text-slate-500">
            Par <span class="font-medium text-slate-700">{{ $article->author?->name }}</span>
            @if ($article->relationLoaded('coauthors') && $article->coauthors->isNotEmpty())
                avec {{ $article->coauthors->pluck('name')->join(', ', ' et ') }}
            @endif
        </p>
    </div>
</article>
