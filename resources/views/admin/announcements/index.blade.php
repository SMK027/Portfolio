<x-app-layout>
    <x-slot name="title">Annonces</x-slot>
    <x-slot name="header">Annonces</x-slot>
    @can('panel', 'announcements.write')
    <x-slot name="actions"><a href="{{ route('admin.annonces.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouvelle annonce</a></x-slot>
    @endcan

    <p class="text-sm text-slate-500">
        Les annonces s'affichent en bandeau sous le menu, sur toutes les pages publiques : recherche de stage ou d'alternance,
        disponibilité, actualité… Plusieurs annonces peuvent être en ligne en même temps.
    </p>

    @if ($announcements->isEmpty())
        <x-empty-state icon="megaphone" message="Aucune annonce pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Annonce</th><th class="hidden md:table-cell">Période</th><th>Statut</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($announcements as $announcement)
                        <tr>
                            <td>
                                <p class="flex items-center gap-2 font-medium text-slate-900">
                                    <x-icon :name="$announcement->icon()" class="h-4 w-4 flex-none text-slate-400" /> {{ $announcement->title }}
                                </p>
                                <p class="text-xs text-slate-500">{{ $announcement->styleLabel() }}@if ($announcement->message) · {{ \Illuminate\Support\Str::limit($announcement->message, 70) }}@endif</p>
                            </td>
                            <td class="hidden whitespace-nowrap text-xs md:table-cell">
                                {{ $announcement->starts_at?->format('d/m/Y H:i') ?? 'Immédiatement' }}<br>
                                <span class="text-slate-400">→ {{ $announcement->ends_at?->format('d/m/Y H:i') ?? 'sans fin' }}</span>
                            </td>
                            <td>
                                @php $status = $announcement->status(); @endphp
                                <span @class(['badge-green' => $status === 'En ligne', 'badge-amber' => $status === 'Programmée', 'badge-slate' => in_array($status, ['Désactivée', 'Expirée'])])>{{ $status }}</span>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link can="announcements.write" :href="route('admin.annonces.edit', $announcement)" />
                                <x-admin.delete-button can="announcements.delete" :action="route('admin.annonces.destroy', $announcement)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
