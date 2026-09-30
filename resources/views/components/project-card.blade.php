@props(['project'])
@php $thumb = $project->thumbnailUrl(); @endphp
<article class="group card relative flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
    <a href="{{ route('projects.show', $project) }}" class="relative block aspect-[16/10] overflow-hidden bg-gradient-to-br from-primary-100 to-accent-100" tabindex="-1" aria-hidden="true">
        @if ($thumb)
            <img src="{{ $thumb }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <span class="absolute inset-0 flex items-center justify-center font-display text-5xl font-bold text-primary-300">{{ mb_strtoupper(mb_substr($project->title, 0, 1)) }}</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-5">
        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="h-3.5 w-3.5" /> {{ $project->published_on->translatedFormat('F Y') }}</span>
            @foreach ($project->themes->take(2) as $theme)
                <span class="badge-primary">{{ $theme->name }}</span>
            @endforeach
        </div>
        <h3 class="font-display text-lg font-semibold text-slate-900">
            <a href="{{ route('projects.show', $project) }}" class="after:absolute after:inset-0 focus:outline-none">{{ $project->title }}</a>
        </h3>
        <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $project->excerpt() }}</p>
        @if ($project->relationLoaded('skills') && $project->skills->isNotEmpty())
            <div class="mt-auto flex flex-wrap gap-1.5 pt-4">
                @foreach ($project->skills->take(4) as $skill)
                    <span class="badge-slate">{{ $skill->name }}</span>
                @endforeach
                @if ($project->skills->count() > 4)
                    <span class="badge-slate">+{{ $project->skills->count() - 4 }}</span>
                @endif
            </div>
        @endif
    </div>
</article>
