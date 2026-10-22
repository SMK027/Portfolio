<x-app-layout>
    <x-slot name="title">Historique d'une IP</x-slot>
    <x-slot name="header">Historique d'une adresse IP</x-slot>

    <p class="text-sm text-slate-500">
        Retrace tout ce qu'une adresse IP a fait sur le site : pages vues, messages de contact, demandes de rendez-vous, opérations du journal d'activité et bannissements.
        Les IP des visites sont effacées au bout de 3 mois : l'historique ne remonte pas au-delà. Chaque consultation est enregistrée dans le journal d'activité.
    </p>

    <form method="GET" class="card flex flex-wrap gap-2 p-4">
        <input type="search" name="ip" value="{{ $ip }}" placeholder="Adresse IPv4 ou IPv6 (ex. 203.0.113.7)" class="form-input min-w-0 flex-1 font-mono" required>
        <button class="btn-primary"><x-icon name="search" class="h-4 w-4" /> Rechercher</button>
    </form>

    @if ($ip !== '' && ! $valid)
        <p class="text-sm text-red-600">« {{ $ip }} » n'est pas une adresse IP valide.</p>
    @elseif ($valid)
        <x-admin.section title="Synthèse">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-slate-500">Localisation</dt><dd class="font-medium text-slate-900">{{ \App\Services\GeoIp::label($summary['country']) }}@if ($summary['location'])<span class="block text-xs font-normal text-slate-500">{{ $summary['location'] }}</span>@endif</dd></div>
                <div><dt class="text-slate-500">Première / dernière visite</dt><dd class="font-medium text-slate-900">@if ($summary['first']){{ $summary['first']->format('d/m/Y H:i') }} → {{ $summary['last']->format('d/m/Y H:i') }}@else — @endif</dd></div>
                <div><dt class="text-slate-500">Pages vues</dt><dd class="font-medium text-slate-900">{{ $summary['pages'] }}@if ($truncated)+@endif <span class="font-normal text-slate-500">· {{ $summary['visits'] }} visite(s) · {{ $summary['visitors'] }} navigateur(s)</span></dd></div>
                <div>
                    <dt class="text-slate-500">Bannissement</dt>
                    <dd>
                        @if ($bans->first(fn ($b) => $b->isActive()))
                            <span class="badge-red">Bannie</span>
                        @else
                            <span class="badge-slate">Non bannie</span>
                            <a href="{{ route('admin.ip-bans.create', ['ip' => $ip]) }}" class="ml-1 text-xs font-medium text-primary-600 hover:underline">Bannir</a>
                        @endif
                        @if ($bans->isNotEmpty())<a href="{{ route('admin.ip-bans.index') }}" class="block text-xs text-slate-500 hover:underline">{{ $bans->count() }} bannissement(s) enregistré(s)</a>@endif
                    </dd>
                </div>
                @if ($summary['devices']->isNotEmpty())
                    <div class="sm:col-span-2 lg:col-span-4"><dt class="text-slate-500">Appareils</dt><dd class="text-slate-700">@foreach ($summary['devices'] as $device => $count)<span class="badge-slate mr-1">{{ $device }} ({{ $count }})</span>@endforeach</dd></div>
                @endif
            </dl>
        </x-admin.section>

        <x-admin.section title="Chronologie">
            @if ($events->isEmpty())
                <x-empty-state icon="clock" message="Aucune activité enregistrée pour cette adresse." />
            @else
                <div class="-mx-4 overflow-x-auto sm:-mx-6">
                    <table class="admin-table">
                        <thead><tr><th>Date</th><th>Type</th><th>Action</th><th class="hidden md:table-cell">Détail</th><th class="w-12"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($events as $event)
                                <tr>
                                    <td class="whitespace-nowrap text-xs text-slate-500">{{ $event['at']?->format('d/m/Y H:i:s') }}</td>
                                    <td class="whitespace-nowrap"><span @class(['badge-slate' => $event['type'] === 'Page vue', 'badge-green' => $event['type'] !== 'Page vue' && $event['type'] !== 'Journal d\'activité', 'badge-amber' => $event['type'] === 'Journal d\'activité'])>{{ $event['type'] }}</span></td>
                                    <td class="max-w-xs truncate text-sm">{{ $event['label'] }}</td>
                                    <td class="hidden max-w-sm truncate text-xs text-slate-500 md:table-cell">{{ $event['detail'] ?? '—' }}</td>
                                    <td class="text-right">@if ($event['url'])<a href="{{ $event['url'] }}" class="inline-flex items-center justify-center align-middle rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ouvrir" aria-label="Ouvrir"><x-icon name="eye" class="h-4 w-4" /></a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($truncated)<p class="text-xs text-slate-400">Seules les {{ \App\Http\Controllers\Admin\IpHistoryController::LIMIT }} dernières pages vues sont affichées.</p>@endif
            @endif
        </x-admin.section>
    @endif
</x-app-layout>
