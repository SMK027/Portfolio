@php
    $max = max(1, $stats['series']->max('views'));
    $trend = $stats['previousViews'] ? round(($stats['views'] - $stats['previousViews']) / $stats['previousViews'] * 100) : null;
@endphp
<x-app-layout>
    <x-slot name="title">Statistiques</x-slot>
    <x-slot name="header">Statistiques de visite</x-slot>
    <x-slot name="actions">
        <div class="flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
            @foreach (\App\Services\SiteStatistics::PERIODS as $value => $label)
                <a href="{{ route('admin.statistics', ['periode' => $value]) }}" @class(['rounded-md px-3 py-1 font-medium', 'bg-primary-50 text-primary-700' => $days === $value, 'text-slate-500 hover:text-slate-900' => $days !== $value])>{{ $label }}</a>
            @endforeach
        </div>
    </x-slot>

    <p class="text-sm text-slate-500">
        Mesure sans cookie ni bandeau : aucune adresse IP n'est conservée, un visiteur n'est reconnu que le temps d'une journée.
        Les personnes connectées, les robots et les navigateurs demandant à ne pas être suivis ne sont pas comptés. Données conservées 13 mois.
    </p>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-sm text-slate-500">Pages vues</p>
            <p class="mt-1 font-display text-3xl font-bold text-slate-900">{{ number_format($stats['views'], 0, ',', ' ') }}</p>
            @if ($trend !== null)
                <p @class(['mt-1 text-xs font-semibold', 'text-emerald-600' => $trend >= 0, 'text-red-600' => $trend < 0])>{{ $trend >= 0 ? '+' : '' }}{{ $trend }} % vs période précédente</p>
            @endif
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Visiteurs <span class="text-xs">(uniques par jour)</span></p>
            <p class="mt-1 font-display text-3xl font-bold text-slate-900">{{ number_format($stats['visitors'], 0, ',', ' ') }}</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Pages par visiteur</p>
            <p class="mt-1 font-display text-3xl font-bold text-slate-900">{{ $stats['visitors'] ? number_format($stats['views'] / $stats['visitors'], 1, ',', ' ') : '—' }}</p>
        </div>
    </div>

    <x-admin.section title="Pages vues par jour">
        @if ($stats['views'] === 0)
            <p class="text-sm text-slate-500">Aucune visite enregistrée sur la période.</p>
        @else
            <div class="flex h-48 items-end gap-px" role="img" aria-label="Pages vues par jour">
                @foreach ($stats['series'] as $day)
                    <div class="group relative flex h-full flex-1 items-end">
                        <div class="w-full rounded-t bg-primary-500/80 transition group-hover:bg-primary-600" style="height: {{ max($day['views'] ? 2 : 0, $day['views'] / $max * 100) }}%"></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                            {{ $day['date']->translatedFormat('D j M') }} : {{ $day['views'] }} vue(s), {{ $day['visitors'] }} visiteur(s)
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-xs text-slate-400">
                <span>{{ $stats['series']->first()['date']->translatedFormat('j M') }}</span>
                <span>{{ $stats['series']->last()['date']->translatedFormat('j M') }}</span>
            </div>
        @endif
    </x-admin.section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-admin.section title="Pages les plus vues">
            @forelse ($stats['topPages'] as $row)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <a href="{{ url($row->path) }}" target="_blank" class="min-w-0 truncate text-slate-700 hover:text-primary-700" title="{{ $row->path }}">{{ $row->title }}</a>
                    <span class="font-semibold text-slate-900">{{ $row->views }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucune donnée.</p>
            @endforelse
        </x-admin.section>
        <x-admin.section title="Sites d'origine">
            @forelse ($stats['referrers'] as $row)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="min-w-0 truncate text-slate-700">{{ $row->referrer_host }}</span>
                    <span class="font-semibold text-slate-900">{{ $row->views }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun lien externe (visites directes ou depuis le site).</p>
            @endforelse
        </x-admin.section>
    </div>
</x-app-layout>
