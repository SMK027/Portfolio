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
        Mesure d'audience à usage statistique uniquement : cookie propre au site (13 mois) pour reconnaître un visiteur qui revient,
        adresses IP effacées après 3 mois, pays déduit localement (aucun service tiers). Les personnes connectées, les robots et les
        navigateurs demandant à ne pas être suivis (DNT / GPC) ne sont pas comptés. Données conservées 13 mois.
    </p>
    @unless ($geoipReady)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Base de pays absente : lancez <code>php artisan portfolio:geoip-update</code> (puis mise à jour mensuelle automatique).</p>
    @endunless

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

    @php $fmt = fn ($s) => \App\Services\SiteStatistics::duration($s); @endphp
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card p-5">
            <p class="text-sm text-slate-500">Visites</p>
            <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ number_format($stats['visits'], 0, ',', ' ') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $stats['pagesPerVisit'] ? number_format($stats['pagesPerVisit'], 1, ',', ' ') : '—' }} page(s) par visite</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Nouveaux / récurrents</p>
            <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ $stats['newVisits'] }} <span class="text-slate-400">/</span> {{ $stats['visits'] - $stats['newVisits'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $stats['visits'] ? round($stats['newVisits'] / $stats['visits'] * 100) : 0 }} % de premières visites</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Temps moyen par visite</p>
            <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ $fmt($stats['avgVisitTime']) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Temps moyen par page</p>
            <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ $fmt($stats['avgPageTime']) }}</p>
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
                    <span class="whitespace-nowrap text-slate-900"><span class="font-semibold">{{ $row->views }}</span> <span class="text-xs text-slate-400">· {{ $fmt($row->avg_duration) }}</span></span>
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

    @php
        $bars = function ($rows, $key, $labels = null) {
            $total = max(1, $rows->sum('views'));
            return $rows->map(fn ($r) => ['label' => $labels ? ($labels[$r->{$key}] ?? $r->{$key}) : $r->{$key}, 'views' => $r->views, 'pct' => round($r->views / $total * 100)]);
        };
    @endphp
    <div class="grid gap-6 lg:grid-cols-2">
        <x-admin.section title="Pays">
            @php $total = max(1, $stats['countries']->sum('views')); @endphp
            @forelse ($stats['countries'] as $row)
                <div class="text-sm">
                    <div class="flex justify-between gap-3"><span>{{ \App\Services\GeoIp::label($row->country) }}</span><span class="text-slate-500"><span class="font-semibold text-slate-900">{{ $row->views }}</span> vues · {{ $row->visitors }} visiteur(s)</span></div>
                    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-primary-500" style="width: {{ round($row->views / $total * 100) }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucune donnée.</p>
            @endforelse
            <p class="text-xs text-slate-400">Géolocalisation : <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline">IP Geolocation by DB-IP</a> (CC BY 4.0).</p>
        </x-admin.section>
        <x-admin.section title="Appareils et navigateurs">
            @foreach ([['Type d\'appareil', $bars($stats['devices'], 'device', \App\Support\UserAgent::DEVICES)], ['Navigateur', $bars($stats['browsers'], 'browser')], ['Système', $bars($stats['systems'], 'os')]] as [$title, $rows])
                <div>
                    <p class="form-label">{{ $title }}</p>
                    @forelse ($rows as $row)
                        <div class="mb-1 text-sm">
                            <div class="flex justify-between"><span>{{ $row['label'] }}</span><span class="text-slate-500">{{ $row['pct'] }} %</span></div>
                            <div class="mt-0.5 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-accent-500" style="width: {{ $row['pct'] }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Aucune donnée.</p>
                    @endforelse
                </div>
            @endforeach
        </x-admin.section>
    </div>

    <x-admin.section title="Dernières pages vues">
        <div class="-mx-4 overflow-x-auto sm:-mx-6">
            <table class="admin-table">
                <thead><tr><th>Date</th>@if ($showIps)<th>IP</th>@endif<th>Pays</th><th>Appareil</th><th>Page</th><th>Visiteur</th><th class="text-right">Durée</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($latest as $view)
                        <tr>
                            <td class="whitespace-nowrap text-xs text-slate-500">{{ $view->created_at?->format('d/m H:i') }}</td>
                            @if ($showIps)<td class="font-mono text-xs">{{ $view->ip_address ?? '—' }}</td>@endif
                            <td class="whitespace-nowrap text-sm">{{ \App\Services\GeoIp::label($view->country) }}</td>
                            <td class="whitespace-nowrap text-sm">{{ \App\Support\UserAgent::DEVICES[$view->device] ?? '—' }}<span class="block text-xs text-slate-400">{{ collect([$view->browser, $view->os])->filter()->join(' · ') }}</span></td>
                            <td class="max-w-xs truncate text-sm"><a href="{{ url($view->path) }}" target="_blank" class="hover:text-primary-700">{{ $view->path }}</a></td>
                            <td class="text-sm">@if ($view->visitor_id)<span @class(['badge-green' => $view->is_new_visitor, 'badge-slate' => ! $view->is_new_visitor])>{{ $view->is_new_visitor ? 'Nouveau' : 'Récurrent' }}</span>@else — @endif</td>
                            <td class="whitespace-nowrap text-right text-sm">{{ $fmt($view->duration) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-sm text-slate-500">Aucune visite enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $latest->links() }}
    </x-admin.section>
</x-app-layout>
