<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        @if ($educations->isEmpty())
            <x-empty-state icon="academic-cap" message="Aucune formation n'a encore été ajoutée." />
        @else
            <ol class="relative space-y-8 border-l-2 border-primary-100 pl-6 sm:pl-8">
                @foreach ($educations as $education)
                    <li class="relative">
                        <span class="absolute -left-[2.05rem] top-5 flex h-4 w-4 items-center justify-center rounded-full border-4 border-white bg-primary-500 ring-2 ring-primary-100 sm:-left-[2.55rem]"></span>
                        <article class="card p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="inline-flex items-center gap-1 font-medium text-primary-700">
                                    <x-icon name="calendar" class="h-4 w-4" />
                                    {{ $education->start_date->translatedFormat('M Y') }} — {{ $education->end_date?->translatedFormat('M Y') ?? 'aujourd\'hui' }}
                                </span>
                                @if ($education->isOngoing())
                                    <span class="badge-green">En cours</span>
                                @endif
                            </div>
                            <h2 class="mt-2 font-display text-xl font-semibold text-slate-900">{{ $education->title }}</h2>
                            <p class="mt-1 text-slate-600">
                                {{ $education->institution }}@if ($education->location)<span class="text-slate-400"> · {{ $education->location }}</span>@endif
                            </p>
                            @if ($education->description)
                                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $education->description }}</p>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-public-layout>
