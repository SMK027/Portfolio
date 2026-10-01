<x-public-layout :title="$query !== '' ? 'Recherche : '.$query : 'Recherche'">
    @push('head')<meta name="robots" content="noindex, follow">@endpush
    <x-page-header title="Recherche" :intro="$query !== '' ? $results->count().' résultat(s) pour « '.$query.' »' : 'Articles, projets, compétences, parcours…'" />

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
        <form method="GET" action="{{ route('search') }}" class="flex gap-2" role="search">
            <input type="search" name="q" value="{{ $query }}" minlength="2" maxlength="100" required autofocus placeholder="Rechercher…" class="form-input flex-1" aria-label="Rechercher">
            <button class="btn-primary"><x-icon name="search" class="h-4 w-4" /> Rechercher</button>
        </form>

        @if ($query !== '' && mb_strlen(trim($query)) < \App\Services\SiteSearch::MIN_LENGTH)
            <p class="text-sm text-slate-500">Saisissez au moins {{ \App\Services\SiteSearch::MIN_LENGTH }} caractères.</p>
        @elseif ($query !== '' && $results->isEmpty())
            <x-empty-state icon="search" message="Aucun résultat. Essayez un autre mot ou une orthographe différente." />
        @endif

        <ul class="space-y-3">
            @foreach ($results as $result)
                <li>
                    <a href="{{ $result['url'] }}" class="card block p-4 transition hover:border-primary-200 hover:shadow-md">
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600">{{ $result['type'] }}</span>
                        <span class="ml-1 font-medium text-slate-900">{{ $result['title'] }}</span>
                        @if ($result['excerpt'])<p class="mt-1 text-sm text-slate-500">{{ $result['excerpt'] }}</p>@endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-public-layout>
