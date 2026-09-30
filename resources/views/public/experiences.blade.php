<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        @if ($experiences->isEmpty())
            <x-empty-state icon="briefcase" message="Aucune expérience n'a encore été ajoutée." />
        @else
            <ol class="relative space-y-8 border-l-2 border-primary-100 pl-6 sm:pl-8">
                @foreach ($experiences as $experience)
                    <li class="relative">
                        <span class="absolute -left-[2.05rem] top-5 flex h-4 w-4 items-center justify-center rounded-full border-4 border-white bg-primary-500 ring-2 ring-primary-100 sm:-left-[2.55rem]"></span>
                        <article class="card p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="inline-flex items-center gap-1 font-medium text-primary-700">
                                    <x-icon name="calendar" class="h-4 w-4" />
                                    {{ $experience->formatDate($experience->start_date) }} — {{ $experience->formatDate($experience->end_date) ?? 'aujourd\'hui' }}
                                </span>
                                @if ($experience->contract_type)<span class="badge-primary">{{ $experience->contract_type }}</span>@endif
                                @if ($experience->isOngoing())<span class="badge-green">En poste</span>@endif
                            </div>
                            <h2 class="mt-2 font-display text-xl font-semibold text-slate-900">{{ $experience->title }}</h2>
                            <p class="mt-1 text-slate-600">
                                {{ $experience->company }}@if ($experience->location)<span class="text-slate-400"> · {{ $experience->location }}</span>@endif
                            </p>
                            @if ($experience->description)
                                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $experience->description }}</p>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-public-layout>
