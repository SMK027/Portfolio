@php $showIp = auth()->user()->isAdmin(); @endphp
<x-app-layout>
    <x-slot name="title">Historique du code {{ $code->formatted() }}</x-slot>
    <x-slot name="header">Historique du code {{ $code->formatted() }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.profile.cv-codes.index') }}" class="btn-secondary">Retour aux codes</a>
    </x-slot>

    <div class="card grid gap-4 p-4 sm:grid-cols-4 sm:p-6">
        <div><p class="text-xs uppercase tracking-wide text-slate-400">Destinataire</p><p class="font-medium text-slate-900">{{ $code->label }}</p></div>
        <div><p class="text-xs uppercase tracking-wide text-slate-400">Téléchargements</p><p class="font-medium text-slate-900">{{ $code->uses }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}</p></div>
        <div><p class="text-xs uppercase tracking-wide text-slate-400">Première utilisation</p><p class="font-medium text-slate-900">{{ $code->usesLog()->oldest('used_at')->value('used_at')?->format('d/m/Y H:i') ?? '—' }}</p></div>
        <div><p class="text-xs uppercase tracking-wide text-slate-400">Dernière utilisation</p><p class="font-medium text-slate-900">{{ $code->last_used_at?->format('d/m/Y H:i') ?? '—' }}</p></div>
    </div>

    <p class="text-sm text-slate-500">
        Provenance approximative déduite de l'adresse IP (base locale, aucune donnée envoyée à un tiers).
        @if ($showIp) Les adresses IP sont effacées après 3 mois. @endif
    </p>

    @if ($uses->isEmpty())
        <x-empty-state icon="clock" message="Ce code n'a pas encore été utilisé (ou ses utilisations précèdent l'historique)." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Date</th><th>Provenance</th><th class="hidden md:table-cell">Appareil</th>@if ($showIp)<th class="hidden md:table-cell">IP</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($uses as $use)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $use->used_at->format('d/m/Y H:i') }}</td>
                            <td class="text-sm">{{ \App\Services\GeoIp::label($use->country) }}@if ($use->city)<span class="block text-xs text-slate-400">{{ $use->city }}</span>@endif</td>
                            <td class="hidden text-sm text-slate-600 md:table-cell">
                                {{ \App\Support\UserAgent::DEVICES[$use->device] ?? '—' }}
                                <span class="block text-xs text-slate-400">{{ collect([$use->browser, $use->os])->filter()->join(' · ') }}</span>
                            </td>
                            @if ($showIp)<td class="hidden font-mono text-xs text-slate-500 md:table-cell">{{ $use->ip_address ?? 'effacée' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $uses->links() }}
    @endif
</x-app-layout>
